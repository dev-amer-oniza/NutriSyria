<?php

namespace App\Services\Import;

/**
 * Validates a staged import bundle. Pure logic: no database access, so it can be unit tested.
 *
 * Row shape for every sheet: ['id' => import_rows.id, 'row' => file row number, 'raw' => [column => value]]
 *
 * Blocking rule: a record is committed only if its own row AND all its nutrient_values rows
 * are error-free. Portion errors never block a record (the portion row is just skipped).
 */
final class BundleValidator
{
    private const PROXIMATE = ['water_g', 'protein_g', 'fat_g', 'carbohydrate_g', 'ash_g'];

    public function __construct(private Lookups $l, private array $cfg) {}

    /**
     * @param  list<array{id:int,row:int,raw:array<string,string>}>  $records
     * @param  list<array{id:int,row:int,raw:array<string,string>}>  $values
     * @param  list<array{id:int,row:int,raw:array<string,string>}>  $portions
     * @param  array<string,array{id:int,food_id:int}>  $existing  "source|ref" => existing DB record
     * @return array{issues:array<int,list<array{level:string,code:string,message:string}>>,rowStatus:array<int,string>,blockedKeys:array<string,true>,issueList:list<array>,report:array}
     */
    public function validate(array $records, array $values, array $portions, array $existing = []): array
    {
        $issues = [];
        $meta = [];   // rowId => [sheet, row]
        $add = function (int $rowId, string $level, string $code, string $message) use (&$issues): void {
            $issues[$rowId][] = ['level' => $level, 'code' => $code, 'message' => $message];
        };

        // ------------------------------------------------------------ records
        $recordRowByKey = [];
        $newFoodByKey = [];   // record key => normalized name of the food it would create
        foreach ($records as $rec) {
            $rid = $rec['id'];
            $raw = $rec['raw'];
            $meta[$rid] = ['records', $rec['row']];
            $key = self::key($raw);

            $ds = $raw['data_source_code'] ?? '';
            $ref = $raw['external_ref'] ?? '';

            if (! isset($this->l->dataSources[$ds])) {
                $add($rid, 'error', 'unknown_data_source', "مصدر البيانات «{$ds}» غير موجود أو غير مفعّل (عمود data_source_code)");
            }
            if ($ref === '') {
                $add($rid, 'error', 'missing_external_ref', 'الرقم المرجعي (external_ref) فارغ: هو ما تجد به إعادة الاستيراد هذا السجل');
            } elseif (isset($recordRowByKey[$key])) {
                $add($rid, 'error', 'duplicate_record', "الرقم المرجعي «{$ref}» مكرر لنفس المصدر في هذا الملف");
            } else {
                $recordRowByKey[$key] = $rid;
            }

            $form = $raw['food_form_code'] ?? '';
            if ($form === '') {
                $add($rid, 'error', 'missing_food_form', 'شكل الغذاء (food_form_code) فارغ');
            } elseif (! isset($this->l->forms[$form])) {
                $add($rid, 'error', 'unknown_food_form', "شكل الغذاء «{$form}» غير معروف (انظر الإعدادات المرجعية ← أشكال الغذاء)");
            }

            $category = trim($raw['category_path'] ?? '');
            if ($category !== '' && $this->l->resolveCategoryPath($category) === null) {
                $add($rid, 'error', 'unknown_category', "التصنيف «{$category}» غير موجود (الاستيراد لا ينشئ تصنيفات)");
            }

            $food = $this->l->resolveFood($raw);
            if ($food['status'] === 'error') {
                $add($rid, 'error', $food['code'], $food['message']);
            } elseif ($food['status'] === 'new') {
                $newFoodByKey[$key] = ArabicNormalizer::normalize($raw['food_name_ar']);
                $add($rid, 'info', 'new_food', "سيُنشأ غذاء جديد «{$raw['food_name_ar']}» (لا يظهر قبل الاعتماد)");
            }

            if (isset($existing[$key]) && $food['status'] !== 'error') {
                if ($food['status'] === 'new' || $food['id'] !== $existing[$key]['food_id']) {
                    $add($rid, 'error', 'record_linked_to_other_food',
                        'هذا العنصر من المصدر مربوط من قبل بغذاء آخر'.$this->foodName($existing[$key]['food_id']).'؛ صحّح اسم الغذاء في المطابقة');
                }
            }
        }

        // ------------------------------------------------------------ nutrient values
        $valuesByKey = [];   // key => [nutrient code => float|null]
        $seenValue = [];
        $cfg = $this->cfg;
        foreach ($values as $val) {
            $rid = $val['id'];
            $raw = $val['raw'];
            $meta[$rid] = ['nutrient_values', $val['row']];
            $key = self::key($raw);
            $code = $raw['nutrient_code'] ?? '';

            if (! isset($recordRowByKey[$key])) {
                $add($rid, 'error', 'orphan_value', 'لا يوجد سجل بنفس المصدر والرقم المرجعي في ملف السجلات');
            }

            $nut = $this->l->nutrients[$code] ?? null;
            if ($nut === null) {
                $add($rid, 'error', 'unknown_nutrient', "المغذّي «{$code}» غير معروف أو غير مفعّل");

                continue;
            }

            $dupKey = $key.'|'.$code;
            if (isset($seenValue[$dupKey])) {
                $add($rid, 'error', 'duplicate_value', "{$this->nutrient($code)} مكرر لنفس السجل");

                continue;
            }
            $seenValue[$dupKey] = true;

            $hadError = false;
            $qualifier = $raw['value_qualifier'] ?? '';
            if ($qualifier !== '' && ! in_array($qualifier, $cfg['value_qualifiers'], true)) {
                $add($rid, 'error', 'bad_qualifier', "قيمة value_qualifier «{$qualifier}» غير مسموحة");
                $hadError = true;
            }

            $amountRaw = $raw['amount_per_100g'] ?? '';
            $amount = null;
            if ($amountRaw === '') {
                if ($qualifier === '') {
                    $add($rid, 'error', 'missing_amount', 'القيمة لكل 100 غ فارغة: احذف السطر بدلها، أو استعمل value_qualifier');
                    $hadError = true;
                }
            } elseif (! is_numeric($amountRaw)) {
                $add($rid, 'error', 'not_numeric', "القيمة لكل 100 غ «{$amountRaw}» ليست رقماً");
                $hadError = true;
            } else {
                $amount = (float) $amountRaw;
                if ($amount < 0) {
                    $add($rid, 'error', 'negative_amount', "{$this->nutrient($code)} سالب ({$amountRaw})");
                    $hadError = true;
                } elseif (strtolower($nut['unit']) === 'g' && $amount > 100) {
                    $add($rid, 'error', 'exceeds_100g', "{$this->nutrient($code)} = {$amountRaw} غ لكل 100 غ: مستحيل");
                    $hadError = true;
                }
            }

            $unit = $raw['unit'] ?? '';
            if ($unit !== '' && strcasecmp($unit, $nut['unit']) !== 0) {
                $add($rid, 'error', 'unit_mismatch', "وحدة {$this->nutrient($code)} عندنا «{$nut['unit']}» والملف يقول «{$unit}»");
                $hadError = true;
            }

            $method = $raw['method'] ?? '';
            if ($method !== '' && ! in_array($method, $cfg['methods'], true)) {
                $add($rid, 'error', 'bad_method', "طريقة القياس «{$method}» غير مسموحة");
                $hadError = true;
            }
            $confidence = $raw['confidence_level'] ?? '';
            if ($confidence !== '' && ! in_array($confidence, $cfg['confidence_levels'], true)) {
                $add($rid, 'error', 'bad_confidence', "مستوى الثقة «{$confidence}» غير مسموح");
                $hadError = true;
            }

            $sampleCount = $raw['sample_count'] ?? '';
            if ($sampleCount !== '' && ! ctype_digit($sampleCount)) {
                $add($rid, 'error', 'bad_sample_count', "عدد العينات «{$sampleCount}» ليس عدداً صحيحاً");
                $hadError = true;
            }

            $bounds = [];
            foreach (['min_amount', 'max_amount'] as $field) {
                $b = $raw[$field] ?? '';
                if ($b === '') {
                    continue;
                }
                if (! is_numeric($b)) {
                    $add($rid, 'error', 'not_numeric', "{$field} «{$b}» ليس رقماً");
                    $hadError = true;
                } else {
                    $bounds[$field] = (float) $b;
                }
            }
            if ($amount !== null && ! $hadError) {
                if ((isset($bounds['min_amount']) && $amount < $bounds['min_amount'] - 1e-9)
                    || (isset($bounds['max_amount']) && $amount > $bounds['max_amount'] + 1e-9)) {
                    $add($rid, 'warning', 'outside_min_max', "{$this->nutrient($code)} = {$amountRaw} خارج الحد الأدنى والأعلى اللذين ذكرهما المصدر");
                }
            }

            if (! $hadError) {
                $valuesByKey[$key][$code] = $amount;
            }
        }

        // ------------------------------------------------------------ record-level scientific checks
        foreach ($valuesByKey as $key => $vals) {
            $rid = $recordRowByKey[$key] ?? null;
            if ($rid === null) {
                continue;
            }
            foreach ($this->scientificWarnings($vals) as [$code, $message]) {
                $add($rid, 'warning', $code, $message);
            }
        }
        foreach ($recordRowByKey as $key => $rid) {
            $missing = [];
            foreach ($this->l->nutrients as $code => $n) {
                if ($n['is_core'] && ! array_key_exists($code, $valuesByKey[$key] ?? [])) {
                    $missing[] = $code;
                }
            }
            if ($missing) {
                $add($rid, 'warning', 'missing_core_nutrients', 'لا قيمة لمغذيات أساسية: '.implode('، ', array_map(fn (string $c) => $this->nutrient($c), $missing)));
            }
        }

        // ------------------------------------------------------------ portions (never block a record)
        $unmapped = [];
        foreach ($portions as $por) {
            $rid = $por['id'];
            $raw = $por['raw'];
            $meta[$rid] = ['portions', $por['row']];

            if (! isset($recordRowByKey[self::key($raw)])) {
                $add($rid, 'error', 'orphan_portion', 'لا يوجد سجل بنفس المصدر والرقم المرجعي في ملف السجلات');
            }

            $label = trim($raw['measure_unit_label'] ?? '');
            if ($label === '') {
                $add($rid, 'error', 'missing_unit_label', 'وحدة الحصة (measure_unit_label) فارغة');
            } elseif ($this->l->resolveUnitLabel($label) === null) {
                $add($rid, 'error', 'unmapped_unit_label', "وحدة الحصة «{$label}» غير معروفة: أضفها كاسم بديل لوحدة في الإعدادات المرجعية (الحصة وحدها لا تُحفظ، والغذاء يُحفظ)");
                $unmapped[mb_strtolower($label)] = ($unmapped[mb_strtolower($label)] ?? 0) + 1;
            }

            $amount = $raw['amount'] ?? '';
            if ($amount !== '' && (! is_numeric($amount) || (float) $amount <= 0)) {
                $add($rid, 'error', 'bad_portion_amount', "كمية الحصة «{$amount}» يجب أن تكون رقماً موجباً");
            }
            $grams = $raw['gram_weight'] ?? '';
            if (! is_numeric($grams) || (float) $grams <= 0) {
                $add($rid, 'error', 'bad_gram_weight', "وزن الحصة بالغرام «{$grams}» يجب أن يكون رقماً موجباً");
            }
            $basis = $raw['basis'] ?? '';
            if ($basis !== '' && ! in_array($basis, $cfg['portion_bases'], true)) {
                $add($rid, 'error', 'bad_basis', "أساس الحصة «{$basis}» غير مسموح");
            }
        }

        return $this->summarise($records, $values, $portions, $issues, $meta, $unmapped, $newFoodByKey, $existing);
    }

    /** "البروتين (protein_g)": the Arabic name when the nutrient is known, the code always. */
    private function nutrient(string $code): string
    {
        $name = $this->l->nutrients[$code]['name_ar'] ?? null;

        return $name ? "{$name} ({$code})" : $code;
    }

    /** " «عدس» (#12)" for a food id, or " (#12)" when its name is unknown. */
    private function foodName(int $id): string
    {
        $name = $this->l->foodName($id);

        return $name !== null ? " «{$name}» (#{$id})" : " (#{$id})";
    }

    /** @param array<string,float|null> $v */
    private function scientificWarnings(array $v): array
    {
        $c = $this->cfg['checks'];
        $out = [];
        $num = fn (string $code): ?float => $v[$code] ?? null;

        if (! in_array(null, array_map($num, self::PROXIMATE), true)) {
            $sum = array_sum(array_map($num, self::PROXIMATE));
            if (abs($sum - 100) > $c['proximate_sum_tolerance_g']) {
                $out[] = ['proximate_sum', sprintf('الماء + البروتين + الدهون + الكربوهيدرات + الرماد = %.1f غ لكل 100 غ (المتوقع قرابة 100)', $sum)];
            }
        }

        $e = $num('energy_kcal');
        $p = $num('protein_g');
        $f = $num('fat_g');
        $ch = $num('carbohydrate_g');
        if ($e !== null && $p !== null && $f !== null && $ch !== null) {
            $estimate = 4 * $p + 9 * $f + 4 * $ch;
            if (abs($e - $estimate) > max($c['atwater_floor_kcal'], $e * $c['atwater_tolerance_pct'] / 100)) {
                $out[] = ['energy_mismatch', sprintf('السعرات المكتوبة %.0f، والمحسوبة من البروتين والدهون والكربوهيدرات قرابة %.0f', $e, $estimate)];
            }
        }

        $tol = $c['rounding_tolerance_g'];
        $fat = $num('fat_g');
        $fractions = array_filter([$num('fat_sat_g'), $num('fat_mono_g'), $num('fat_poly_g')], fn ($x) => $x !== null);
        if ($fat !== null && $fractions && array_sum($fractions) > $fat + $tol) {
            $out[] = ['fat_fractions_exceed_total', sprintf('الدهون المشبعة والأحادية والمتعددة معاً %.1f غ، والدهون الكلية %.1f غ', array_sum($fractions), $fat)];
        }
        if ($ch !== null) {
            foreach (['sugars_g' => 'السكريات', 'fiber_g' => 'الألياف'] as $code => $label) {
                if (($num($code) ?? 0) > $ch + $tol) {
                    $out[] = ["{$code}_exceed_carbohydrate", sprintf('%s %.1f غ، أكثر من الكربوهيدرات %.1f غ', $label, $num($code), $ch)];
                }
            }
        }

        return $out;
    }

    private function summarise(array $records, array $values, array $portions, array $issues, array $meta, array $unmapped, array $newFoodByKey, array $existing): array
    {
        $rowStatus = [];
        foreach ([$records, $values, $portions] as $sheet) {
            foreach ($sheet as $r) {
                $hasError = false;
                foreach ($issues[$r['id']] ?? [] as $i) {
                    $hasError = $hasError || $i['level'] === 'error';
                }
                $rowStatus[$r['id']] = $hasError ? 'error' : 'valid';
            }
        }

        $blocked = [];
        foreach ($records as $r) {
            if ($rowStatus[$r['id']] === 'error') {
                $blocked[self::key($r['raw'])] = true;
            }
        }
        foreach ($values as $r) {
            if ($rowStatus[$r['id']] === 'error') {
                $blocked[self::key($r['raw'])] = true;
            }
        }

        $counts = ['warning' => [], 'error' => []];
        $issueList = [];
        foreach ($issues as $rowId => $list) {
            foreach ($list as $i) {
                if ($i['level'] === 'info') {
                    continue;
                }
                $counts[$i['level']][$i['code']] = ($counts[$i['level']][$i['code']] ?? 0) + 1;
                $issueList[] = ['sheet' => $meta[$rowId][0], 'row' => $meta[$rowId][1]] + $i;
            }
        }
        usort($issueList, fn ($a, $b) => [$a['level'] === 'error' ? 0 : 1, $a['sheet'], $a['row']] <=> [$b['level'] === 'error' ? 0 : 1, $b['sheet'], $b['row']]);
        arsort($unmapped);

        $count = fn (array $rows, string $status) => count(array_filter($rows, fn ($r) => $rowStatus[$r['id']] === $status));
        $blockedRecordCount = count(array_filter($records, fn ($r) => isset($blocked[self::key($r['raw'])])));

        return [
            'issues' => $issues,
            'rowStatus' => $rowStatus,
            'blockedKeys' => $blocked,
            'issueList' => $issueList,
            'report' => [
                'records' => [
                    'total' => count($records),
                    'ready' => count($records) - $blockedRecordCount,
                    'blocked' => $blockedRecordCount,
                    'new_foods' => count(array_unique(array_diff_key($newFoodByKey, $blocked))),
                    'existing_records' => count($existing),
                ],
                'values' => ['total' => count($values), 'ok' => $count($values, 'valid'), 'errors' => $count($values, 'error')],
                'portions' => ['total' => count($portions), 'ok' => $count($portions, 'valid'), 'errors' => $count($portions, 'error')],
                'warnings' => $counts['warning'],
                'errors' => $counts['error'],
                'unmapped_unit_labels' => array_slice($unmapped, 0, 20, true),
            ],
        ];
    }

    /** @param array<string,string> $raw */
    public static function key(array $raw): string
    {
        return trim($raw['data_source_code'] ?? '').'|'.trim($raw['external_ref'] ?? '');
    }
}
