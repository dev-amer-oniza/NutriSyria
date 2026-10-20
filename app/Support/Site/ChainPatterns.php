<?php

namespace App\Support\Site;

use InvalidArgumentException;

/**
 * The identity's chain-reaction tile compositions, kept in one place so the
 * views only name a pattern (site.components.chain-tiles).
 *
 * A pattern is a row-major list of tokens:
 *   "-"            empty cell
 *   "f"            full pine square
 *   "q-tl" .. "q-br", "p-a", "p-b"   quarter arc / petal shapes
 *   "s." "t." "d." "u." prefix       sage, terracotta (the origin), sand, surface
 *   "@3" suffix    distance from the origin, used by the ripple animation
 */
final class ChainPatterns
{
    private const COLORS = ['s' => 'sage', 't' => 'terracotta', 'd' => 'sand', 'u' => 'surface'];

    private const PATTERNS = [
        'hero' => [7, '- q-bl@3 q-tl@2 q-tr@1 f@2 q-tr@3 s.q-tr@4 f@3 q-tl@2 - t.q-br@0 q-tr@1 q-br@2 f@3 q-tl@4 q-tr@3 q-br@2 - p-b@2 p-a@3 p-b@4 - f@4 p-a@3 q-tl@2 q-bl@3 p-b@4 - s.p-a@6 - q-tl@4 q-bl@3 f@4 s.p-b@5 - p-a@7 p-b@6 p-a@5 q-br@4 q-bl@5 q-br@6 - q-br@8 q-tr@7 s.p-a@6 p-a@5 - - - q-bl@9 q-tl@8 p-a@7 q-tl@6 s.q-tl@7 s.f@8 - q-tl@10 q-tr@9 q-tl@8 q-tr@7 q-tl@8 q-tr@9 -'],

        'dish-1' => [6, '- q-tl q-bl q-tl q-bl t.q-bl s.p-a s.q-tl - q-bl q-br - s.q-br q-br q-bl q-br q-bl q-tl'],
        'dish-2' => [6, 's.q-tl q-tr f s.p-a - t.q-bl s.q-tr s.q-tl q-tl p-a p-b q-bl q-tr q-tl - s.q-bl s.p-b s.q-bl'],
        'dish-3' => [6, 'p-a - s.q-tl s.q-bl q-bl t.q-bl p-b q-bl q-tl - q-tl q-bl s.p-b - p-a q-tl q-bl p-b'],
        'dish-4' => [6, '- q-br s.q-br s.q-bl q-bl t.q-bl - q-bl p-b q-bl q-tl q-bl - s.p-a s.p-b - p-a s.q-bl'],

        'step-1' => [1, 't.q-bl'],
        'step-2' => [1, 'q-tl'],
        'step-3' => [1, 'p-a'],
        'step-4' => [2, 'p-a s.q-bl s.q-tr p-b'],

        'trust' => [5, 'd.q-tl d.q-bl d.q-tl d.q-bl t.q-bl - d.f - d.q-br - - - s.p-a s.q-br d.q-br - s.q-bl s.p-b s.q-tr - d.q-br - s.p-a - s.p-a d.q-tr d.p-b s.p-b s.p-a s.p-b'],
        'cta' => [6, 'p-a - s.q-tl s.q-bl q-bl t.q-bl q-tl q-bl q-br q-bl s.q-bl - - s.q-bl s.q-tl q-tl q-bl q-tl s.q-bl q-bl s.q-bl p-a s.q-bl s.q-tl'],
    ];

    /** Cover patterns for dish cards without a photo, picked by a stable key. */
    public static function dishCover(int|string $key): string
    {
        return 'dish-'.(crc32((string) $key) % 4 + 1);
    }

    /**
     * @return array{cols: int, tiles: list<array{class: string, d: ?int}>}
     */
    public static function get(string $name): array
    {
        [$cols, $pattern] = self::PATTERNS[$name] ?? throw new InvalidArgumentException("Unknown chain pattern [{$name}].");

        return ['cols' => $cols, 'tiles' => array_map(self::tile(...), explode(' ', $pattern))];
    }

    /** @return array{class: string, d: ?int} */
    private static function tile(string $token): array
    {
        [$token, $distance] = array_pad(explode('@', $token, 2), 2, null);

        if ($token === '-') {
            return ['class' => 'ns-tile ns-tile--empty', 'd' => null];
        }

        $classes = ['ns-tile'];

        if (str_contains($token, '.')) {
            [$color, $token] = explode('.', $token, 2);
            $classes[] = 'ns-tile--'.self::COLORS[$color];
        }

        if ($token !== 'f') {
            $classes[] = 'ns-tile--'.$token;
        }

        return ['class' => implode(' ', $classes), 'd' => $distance === null ? null : (int) $distance];
    }
}
