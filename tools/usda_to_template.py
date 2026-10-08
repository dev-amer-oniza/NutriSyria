#!/usr/bin/env python3
"""
usda_to_template.py (v2) - USDA FoodData Central bulk CSVs -> NutriLink import bundle.
Works from local files only: no API key, no rate limit.

Setup (one time)
  1. Download the CSV zips from https://fdc.nal.usda.gov/download-datasets
     (Foundation Foods and SR Legacy; skip Branded). Each zip contains files with the SAME
     names (food.csv, food_nutrient.csv ...), so unzip each into its OWN folder, e.g.
     ./fdc/sr_legacy and ./fdc/foundation, and run the script once per folder
     (own crosswalk, own --out).
  2. Point --nutrients / --source-codes at database/seeders/data/nutrients.csv and
     nutrient_source_codes.csv (or at a DB export with the same columns).

Commands
  verify-map   check every mapped FDC nutrient id against the real nutrient.csv (run this first)
  search       list candidate FDC foods for one search term
  suggest      wanted-foods list -> DRAFT crosswalk (top candidates per food and form, best guess
               pre-marked "yes"); the supervisor only reviews the pick column, then runs build
  build        crosswalk -> records.csv, nutrient_values.csv, portions.csv
  template     empty hand-entry header (record columns + nutrient codes)

Crosswalk (usda_crosswalk.csv) - the one human decision per USDA record:
  fdc_id, food_form_code, food_id OR food_name_ar, [food_name_en, category_path]
  If the file has a `pick` column (a suggest draft), build keeps only rows marked yes/y/x/1.

Wanted-foods list (for suggest): food_name_ar, food_name_en, forms, [search_terms], [exclude]
  forms = semicolon list of raw;cooked;boiled;fried;canned;dried;plain;any
  search_terms = semicolon list of alternative English names (default: food_name_en)
  exclude = semicolon list of words/phrases that remove a candidate (e.g. liver;yolk;green)
  A candidate described as frozen/canned/dried/pickled is skipped unless that is the wanted form.
"""
import argparse
import re
import sys
from pathlib import Path

import pandas as pd

SOURCE_SYSTEM = "usda_fdc"
DATASETS = {  # FDC food.csv data_type -> data_sources.code
    "foundation_food": "usda_fdc_foundation",
    "sr_legacy_food": "usda_fdc_sr_legacy",
    "survey_fndds_food": "usda_fdc_fndds",
}
EXCLUDE = ("babyfood", "fast foods", "restaurant", "school lunch", "infant", "formula")
FORM_RULES = [  # first match wins: specific cooking methods before generic ones
    ("boiled", r"\b(hard-?boiled|boiled|poached)\b"),
    ("fried", r"\b(fried|pan-?fried|deep-?fried)\b"),
    ("roasted", r"\broasted\b"),
    ("baked", r"\bbaked\b"),
    ("grilled", r"\b(grilled|broiled)\b"),
    ("steamed", r"\bsteamed\b"),
    ("canned", r"\bcanned\b"),
    ("frozen", r"\bfrozen\b"),
    ("pickled", r"\bpickled\b"),
    ("strained", r"\bstrained\b"),
    ("dried", r"\b(dried|dehydrated)\b"),
    ("cooked", r"\bcooked\b"),
    ("raw", r"\braw\b"),
    ("dry", r"\bdry\b"),
]
COOKED_FORMS = {"cooked", "boiled", "steamed", "baked", "grilled", "roasted"}
UNIT_ALIASES = {"µg": "ug", "μg": "ug", "mcg": "ug"}
RECORD_COLS = [
    "food_id", "food_name_ar", "food_name_en", "category_path", "food_form_code",
    "data_source_code", "external_ref", "notes",
]


def norm_unit(u) -> str:
    u = str(u).strip().lower()
    return UNIT_ALIASES.get(u, u)


def squash(s) -> str:
    return re.sub(r"[^a-z0-9]", "", str(s).lower())


def read(folder, name, required=False, **kw):
    path = Path(folder) / name
    if not path.exists():
        if required:
            sys.exit(f"Missing {path}")
        return None
    return pd.read_csv(path, dtype=str, encoding="utf-8-sig", **kw)


def read_codes(path) -> pd.DataFrame:
    codes = pd.read_csv(path, dtype=str, encoding="utf-8-sig")
    return codes[codes.source_system == SOURCE_SYSTEM].copy()


# --------------------------------------------------------------------- verify-map
def cmd_verify_map(a):
    fdc = read(a.fdc_dir, "nutrient.csv", required=True)
    info = fdc.set_index("id")[["name", "unit_name"]].to_dict("index")
    bad = 0
    for r in read_codes(a.source_codes).itertuples():
        got = info.get(r.code)
        if got is None:
            print(f"MISSING  {r.code:>5} {r.nutrient_code}: id not in this download's nutrient.csv")
            bad += 1
            continue
        if float(r.factor) == 1.0 and norm_unit(got["unit_name"]) != norm_unit(r.source_unit):
            print(f"ERROR    {r.code:>5} {r.nutrient_code}: unit {got['unit_name']!r}, seed expects {r.source_unit!r}")
            bad += 1
            continue
        a_, b_ = squash(r.source_name), squash(got["name"])
        status = "OK      " if (a_ in b_ or b_ in a_) else "REVIEW  "
        if status.strip() == "REVIEW":
            print(f"{status} {r.code:>5} {r.nutrient_code}: FDC calls it {got['name']!r}, seed says {r.source_name!r}")
    print("verify-map:", "problems found" if bad else "no missing ids / unit errors (REVIEW lines above are name differences only)")
    sys.exit(1 if bad else 0)


# --------------------------------------------------------------------- search
def cmd_search(a):
    food = read(a.fdc_dir, "food.csv", required=True,
                usecols=["fdc_id", "data_type", "description", "publication_date"])
    food = food[food.data_type.isin(DATASETS)]
    mask = pd.Series(True, index=food.index)
    for term in a.terms:
        mask &= food.description.str.contains(term, case=False, regex=False, na=False)
    order = {"foundation_food": 0, "sr_legacy_food": 1, "survey_fndds_food": 2}
    hits = food[mask].assign(_o=lambda d: d.data_type.map(order)).sort_values(["_o", "description"])
    if hits.empty:
        sys.exit("No matches in Foundation / SR Legacy / FNDDS.")
    print(hits.drop(columns="_o").head(a.limit).to_string(index=False, max_colwidth=90))
    if len(hits) > a.limit:
        print(f"... {len(hits) - a.limit} more; add terms or raise --limit")


# --------------------------------------------------------------------- suggest
def stem(w: str) -> str:
    if w.endswith("oes") and len(w) > 4:
        return w[:-2]
    if w.endswith("ss") or len(w) <= 3:
        return w
    return w[:-1] if w.endswith("s") else w


def words(text: str) -> list:
    return [stem(w) for w in re.findall(r"[a-z]+", str(text).lower())]


STATE_RULES = [
    ("frozen", r"\bfrozen\b"),
    ("canned", r"\bcanned\b"),
    ("dried", r"\b(dried|dehydrated|freeze-dried)\b"),
    ("pickled", r"\bpickled\b"),
    ("strained", r"\bstrained\b"),
]


def guess_state(desc: str) -> str:
    for state, rx in STATE_RULES:
        if re.search(rx, desc, re.I):
            return state
    return ""


def has_word(text: str, phrase: str) -> bool:
    return re.search(rf"\b{re.escape(phrase)}\b", text) is not None


def guess_form(desc: str) -> str:
    for form, rx in FORM_RULES:
        if re.search(rx, desc, re.I):
            return form
    return ""


def fit_form(target: str, guess: str):
    """(food_form_code, note) if a candidate whose description suggests `guess` fits the wanted `target`."""
    assumed = "form not stated by USDA; assumed from your wanted list"
    if target in ("any", "-", "\u2014", "plain", ""):
        return ("as_sold" if guess in ("", "dry") else guess), ""
    if target == "raw":
        if guess == "raw":
            return "raw", ""
        return ("raw", assumed) if guess in ("dry", "") else None
    if target == "dried":
        if guess == "dried":
            return "dried", ""
        return ("dried", assumed) if guess in ("dry", "") else None
    if target == "cooked":
        return (guess, "") if guess in COOKED_FORMS else None
    return (target, "") if guess == target else None


def score_description(desc: str, term_words: list):
    """None if the description does not match any term; else (score, match_label)."""
    low = desc.lower()
    if any(x in low for x in EXCLUDE):
        return None
    clean = re.sub(r"\([^)]*\)", "", desc)
    segments = [seg for seg in clean.split(",")]
    head = words(segments[0])
    lead = words(" ".join(segments[:2]))
    paren = set(words(" ".join(re.findall(r"\(([^)]*)\)", desc))))
    have = set(words(desc))
    best = None
    for tw in term_words:
        if not tw or not all(w in have for w in tw):
            continue
        if set(head) == set(tw):
            cand = (60, "exact")
        elif all(w in paren for w in tw):
            cand = (50, "alias")
        elif set(lead) == set(tw):
            cand = (55, "exact")
        elif head[: len(tw)] == tw:
            cand = (30, "starts-with")
        else:
            cand = (10, "loose")
        best = cand if best is None or cand[0] > best[0] else best
    if best is None:
        return None
    score = best[0]
    if "without salt" in low or "unsalted" in low:
        score += 8
    elif "with salt" in low or "salt added" in low:
        score -= 25
    score -= len(desc) * 0.15  # shorter = more generic
    return round(score, 1), best[1]


def cmd_suggest(a):
    food = read(a.fdc_dir, "food.csv", required=True,
                usecols=["fdc_id", "data_type", "description", "publication_date"])
    food = food[food.data_type.isin(DATASETS)]
    catalog = [{"fdc_id": r.fdc_id, "desc": r.description, "guess": guess_form(r.description),
                "state": guess_state(r.description)}
               for r in food.itertuples() if isinstance(r.description, str)]

    wanted = pd.read_csv(a.wanted, dtype=str, encoding="utf-8-sig").fillna("")
    for col in ("food_name_ar", "food_name_en"):
        if col not in wanted.columns:
            sys.exit(f"Wanted list needs a '{col}' column")

    out = []
    for w in wanted.to_dict("records"):
        terms = [t.strip() for t in (w.get("search_terms") or w["food_name_en"]).split(";") if t.strip()]
        term_words = [words(t) for t in terms]
        targets = [t.strip().lower() for t in (w.get("forms") or "any").split(";") if t.strip()] or ["any"]

        exclude = [t.strip().lower() for t in (w.get("exclude") or "").split(";") if t.strip()]

        scored = []
        for c in catalog:
            if any(has_word(c["desc"].lower(), x) for x in exclude):
                continue
            res = score_description(c["desc"], term_words)
            if res:
                scored.append({**c, "score": res[0], "match": res[1]})

        for target in targets:
            fits = []
            for c in scored:
                if target == "plain" and "plain" not in c["desc"].lower():
                    continue
                if c["state"] and c["state"] != target and target not in ("any", "-", "\u2014", "plain", ""):
                    continue  # frozen/canned/dried/pickled item, but that state was not asked for
                fit = fit_form(target, c["guess"])
                if fit:
                    fits.append({**c, "form_code": fit[0], "form_note": fit[1]})
            fits.sort(key=lambda c: (-c["score"], c["fdc_id"]))
            if not fits:
                out.append({"pick": "", "hint": "", "food_name_ar": w["food_name_ar"],
                            "food_name_en": w["food_name_en"], "target_form": target, "food_form_code": "", "fdc_id": "",
                            "description": "NO CANDIDATE FOUND - search manually or add the data yourself",
                            "match": "", "form_note": "", "score": "", "rank": ""})
            gap = (fits[0]["score"] - fits[1]["score"]) if len(fits) > 1 else None
            for rank, c in enumerate(fits[: a.top], start=1):
                strong = c["match"] in ("exact", "alias")
                clear = gap is None or gap >= a.min_gap
                confident = rank == 1 and strong and clear
                hint = ""
                if rank == 1 and not confident:
                    hint = "weak name match: check" if not strong else "several similar candidates: choose one"
                out.append({"pick": "yes" if confident else "", "hint": hint, "food_name_ar": w["food_name_ar"],
                            "food_name_en": w["food_name_en"], "target_form": target,
                            "food_form_code": c["form_code"], "fdc_id": c["fdc_id"],
                            "description": c["desc"], "match": c["match"], "form_note": c["form_note"],
                            "score": c["score"], "rank": rank})

    df = pd.DataFrame(out)
    df.to_csv(a.out, index=False, encoding="utf-8-sig")  # BOM so Excel shows Arabic correctly
    top = df[df["rank"].astype(str) == "1"]
    marked = int((top.pick == "yes").sum())
    empty = int((df.fdc_id == "").sum())
    print(f"draft: {len(df)} rows for {len(wanted)} foods -> {a.out}")
    print(f"  clear best guess pre-marked yes: {marked} of {len(top) + empty} wanted food/form pairs")
    print(f"  you must choose: {len(top) - marked} (several similar candidates or weak match) | no candidate at all: {empty}")



# --------------------------------------------------------------------- build
def load_nutrient_map(a, fdc_nutrient) -> pd.DataFrame:
    nut = pd.read_csv(a.nutrients, dtype=str, encoding="utf-8-sig").rename(columns={"code": "nutrient_code"})
    keep = ["nutrient_code", "unit"] + (["is_core"] if "is_core" in nut.columns else [])
    m = read_codes(a.source_codes).merge(nut[keep], on="nutrient_code", how="left")
    if m.unit.isna().any():
        sys.exit(f"nutrient_source_codes references unknown nutrients: {m[m.unit.isna()].nutrient_code.tolist()}")
    m["factor"] = pd.to_numeric(m.factor).fillna(1.0)
    m["priority"] = pd.to_numeric(m.priority).fillna(100).astype(int)

    fdc_unit = dict(zip(fdc_nutrient.id, fdc_nutrient.unit_name.map(norm_unit)))
    unit_bad = m.apply(lambda r: r.factor == 1.0 and fdc_unit.get(r.code) != norm_unit(r.unit), axis=1)
    for r in m[unit_bad].itertuples():
        print(f"WARNING unit mismatch or unknown id, skipped: {r.nutrient_code} <- FDC {r.code} "
              f"(ours {r.unit!r}, FDC {fdc_unit.get(r.code)!r})", file=sys.stderr)
    return m[~unit_bad]


def cmd_build(a):
    out = Path(a.out)
    out.mkdir(parents=True, exist_ok=True)

    cw = pd.read_csv(a.crosswalk, dtype=str, encoding="utf-8-sig").fillna("")
    for col in ("fdc_id", "food_form_code"):
        if col not in cw.columns:
            sys.exit(f"Crosswalk needs a '{col}' column")
    if "pick" in cw.columns:  # a suggest draft: only reviewed rows count
        cw = cw[cw.pick.str.strip().str.lower().isin(["yes", "y", "x", "1", "true"])]
        if cw.empty:
            sys.exit("No rows have pick = yes in the crosswalk.")
    cw = cw[cw.fdc_id != ""].reset_index(drop=True)
    for col in ("food_id", "food_name_ar", "food_name_en", "category_path"):
        if col not in cw.columns:
            cw[col] = ""
    # A reviewed `suggest` draft carries extra columns (description, score, hint...). Keep only
    # what build needs, otherwise they clash with the same-named columns in USDA's food.csv.
    cw = cw[["fdc_id", "food_form_code", "food_id", "food_name_ar", "food_name_en", "category_path"]].copy()
    bad = cw[(cw.food_id == "") & (cw.food_name_ar == "")]
    if not bad.empty:
        sys.exit(f"Crosswalk rows without food_id or food_name_ar: fdc_id {bad.fdc_id.tolist()}")
    if cw.fdc_id.duplicated().any():
        sys.exit(f"Duplicate fdc_id in crosswalk: {cw.fdc_id[cw.fdc_id.duplicated()].tolist()}")
    same = (cw.food_id.where(cw.food_id != "", cw.food_name_ar) + " | " + cw.food_form_code)
    if same.duplicated().any():
        sys.exit("More than one USDA item is picked for the same food and form (choose only one): "
                 f"{sorted(set(same[same.duplicated(keep=False)]))}")
    wanted = set(cw.fdc_id)

    # ---- records ----
    food = read(a.fdc_dir, "food.csv", required=True,
                usecols=["fdc_id", "data_type", "description", "publication_date"])
    food = food[food.fdc_id.isin(wanted)]
    missing = wanted - set(food.fdc_id)
    if missing:
        sys.exit(f"fdc_id not found in food.csv: {sorted(missing)}")
    unsupported = food[~food.data_type.isin(DATASETS)]
    if not unsupported.empty:
        sys.exit("Only Foundation / SR Legacy / FNDDS are accepted. Offending: "
                 f"{unsupported[['fdc_id', 'data_type']].values.tolist()}")

    rec = cw.merge(food, on="fdc_id")
    rec_out = pd.DataFrame({
        "food_id": rec.food_id, "food_name_ar": rec.food_name_ar,
        "food_name_en": rec.food_name_en, "category_path": rec.category_path,
        "food_form_code": rec.food_form_code,
        "data_source_code": rec.data_type.map(DATASETS),
        "external_ref": rec.fdc_id,
        "notes": "FDC: " + rec.description.fillna("") + " (published " + rec.publication_date.fillna("?") + ")",
    })[RECORD_COLS]
    rec_out.to_csv(out / "records.csv", index=False)
    ds_of = dict(zip(rec_out.external_ref, rec_out.data_source_code))

    # ---- nutrient values ----
    fdc_nutrient = read(a.fdc_dir, "nutrient.csv", required=True)
    nmap = load_nutrient_map(a, fdc_nutrient)

    path = Path(a.fdc_dir) / "food_nutrient.csv"
    header = pd.read_csv(path, nrows=0).columns
    cols = ["fdc_id", "nutrient_id", "amount", "data_points", "derivation_id", "min", "max"]
    chunks = [
        ch[ch.fdc_id.isin(wanted) & ch.nutrient_id.isin(set(nmap.code))]
        for ch in pd.read_csv(path, dtype=str, usecols=[c for c in cols if c in header], chunksize=500_000)
    ]
    vals = pd.concat(chunks).reindex(columns=cols)
    vals["amount"] = pd.to_numeric(vals.amount, errors="coerce")
    vals = vals.dropna(subset=["amount"])
    neg = vals[vals.amount < 0]
    if not neg.empty:
        names = dict(zip(rec_out.external_ref, rec_out.food_name_ar))
        for r in neg.itertuples():
            print(f"WARNING negative amount dropped: {names.get(r.fdc_id, r.fdc_id)} "
                f"(fdc_id {r.fdc_id}), FDC nutrient_id {r.nutrient_id} = {r.amount} "
                "- raw USDA source error, value excluded, add it back manually if you can confirm it",
                file=sys.stderr)
        vals = vals[vals.amount >= 0]

    vals = vals.merge(nmap[["code", "nutrient_code", "unit", "factor", "priority"]],
                      left_on="nutrient_id", right_on="code")
    vals["amount"] = (vals.amount * vals.factor).round(6)
    before = len(vals)
    vals["_row"] = range(len(vals))  # tie-break = source file order, so results never depend on sort internals
    vals = (vals.sort_values(["fdc_id", "nutrient_code", "priority", "_row"])
                .drop_duplicates(["fdc_id", "nutrient_code"], keep="first"))
    if before != len(vals):
        print(f"note: {before - len(vals)} value(s) had several source codes for one nutrient; "
              "kept the lowest priority number.", file=sys.stderr)

    der = read(a.fdc_dir, "food_nutrient_derivation.csv")
    der_map = {}
    if der is not None:
        der = der.fillna("")
        der_map = dict(zip(der.id, der.code + ": " + der.description))
    nv = pd.DataFrame({
        "data_source_code": vals.fdc_id.map(ds_of), "external_ref": vals.fdc_id,
        "nutrient_code": vals.nutrient_code, "amount_per_100g": vals.amount, "unit": vals.unit,
        "method": "imported",  # USDA derivation text is kept in notes, not guessed into our enum
        "sample_count": vals.data_points, "min_amount": vals["min"], "max_amount": vals["max"],
        "notes": vals.derivation_id.map(der_map).fillna(""),
    })
    nv.to_csv(out / "nutrient_values.csv", index=False)

    # ---- portions ----
    por_n = 0
    por = read(a.fdc_dir, "food_portion.csv")
    if por is not None and por.fdc_id.isin(wanted).any():  # skip cleanly when none of the foods has portions
        mu = read(a.fdc_dir, "measure_unit.csv")
        mu_name = dict(zip(mu.id, mu.name)) if mu is not None else {}
        por = por[por.fdc_id.isin(wanted)].copy()
        por["gram_weight"] = pd.to_numeric(por.gram_weight, errors="coerce")
        por = por.dropna(subset=["gram_weight"])
        unit = por.measure_unit_id.map(mu_name)
        usable = unit.notna() & (unit.str.lower() != "undetermined")
        label = unit.where(usable, por.get("modifier")).fillna(por.get("portion_description"))
        por_out = pd.DataFrame({
            "data_source_code": por.fdc_id.map(ds_of), "external_ref": por.fdc_id,
            "measure_unit_label": label,  # resolved through measure_unit_aliases
            "amount": pd.to_numeric(por.get("amount"), errors="coerce").fillna(1),
            "gram_weight": por.gram_weight,
            "description": por.get("portion_description"),
            "modifier": por.get("modifier"), "basis": "usda",
        })
        por_out.to_csv(out / "portions.csv", index=False)
        por_n = len(por_out)

    # ---- quality report ----
    print(f"records: {len(rec_out)} | nutrient values: {len(nv)} | portions: {por_n} -> {out}/")
    if "is_core" in nmap.columns:
        core = set(nmap[nmap.is_core.isin(["1", "true", "True"])].nutrient_code)
        have = nv.groupby("external_ref").nutrient_code.agg(set)
        for fid in sorted(wanted):
            gap = core - have.get(fid, set())
            if gap:
                print(f"  fdc_id {fid}: missing core nutrients {sorted(gap)}")


def cmd_template(a):
    nut = pd.read_csv(a.nutrients, dtype=str, encoding="utf-8-sig")
    pd.DataFrame(columns=RECORD_COLS + nut.code.tolist()).to_csv(a.out, index=False)
    print(f"wrote {a.out}")


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = p.add_subparsers(dest="cmd", required=True)

    v = sub.add_parser("verify-map")
    v.add_argument("--fdc-dir", required=True)
    v.add_argument("--source-codes", required=True)
    v.set_defaults(fn=cmd_verify_map)

    s = sub.add_parser("search")
    s.add_argument("terms", nargs="+")
    s.add_argument("--fdc-dir", required=True)
    s.add_argument("--limit", type=int, default=40)
    s.set_defaults(fn=cmd_search)

    g = sub.add_parser("suggest")
    g.add_argument("--fdc-dir", required=True)
    g.add_argument("--wanted", required=True)
    g.add_argument("--out", default="draft_crosswalk.csv")
    g.add_argument("--top", type=int, default=3)
    g.add_argument("--min-gap", type=float, default=10.0,
                   help="pre-mark the best candidate only if it beats the runner-up by this many score points")
    g.set_defaults(fn=cmd_suggest)

    b = sub.add_parser("build")
    b.add_argument("--fdc-dir", required=True)
    b.add_argument("--nutrients", required=True)
    b.add_argument("--source-codes", required=True)
    b.add_argument("--crosswalk", required=True)
    b.add_argument("--out", default="import_bundle")
    b.set_defaults(fn=cmd_build)

    t = sub.add_parser("template")
    t.add_argument("--nutrients", required=True)
    t.add_argument("--out", default="hand_entry_template.csv")
    t.set_defaults(fn=cmd_template)

    a = p.parse_args()
    a.fn(a)


if __name__ == "__main__":
    main()
