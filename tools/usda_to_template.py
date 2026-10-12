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

Several releases at once
  suggest and build accept --fdc-dir more than once (e.g. Foundation AND SR Legacy). suggest
  ranks each release on its own and may pre-mark the best item of each; both are imported for
  the same food and form, recipes use the higher-priority one (data_sources.priority) and fill
  the nutrients it lacks from the other. Superseded Foundation versions are never offered, and
  an item without protein/fat/carbohydrate is never pre-marked ("incomplete analysis").

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


def current_foods(folder) -> pd.DataFrame:
    """food.csv rows of the supported datasets, without superseded Foundation versions.

    A Foundation release keeps older analyses of the same food in food.csv (e.g. two
    "Milk, whole, 3.25% milkfat" rows from 2019); foundation_food.csv lists only the
    current ones, so anything else of type foundation_food is an outdated version."""
    food = read(folder, "food.csv", required=True,
                usecols=["fdc_id", "data_type", "description", "publication_date"])
    food = food[food.data_type.isin(DATASETS)]
    current = read(folder, "foundation_food.csv", usecols=["fdc_id"])
    if current is not None:
        food = food[(food.data_type != "foundation_food") | food.fdc_id.isin(set(current.fdc_id))]
    return food


# FDC nutrient ids that answer "does this food have energy / protein / fat / carbohydrate?"
COMPLETENESS = {
    "energy": {"1008", "2047", "2048"},
    "protein": {"1003"},
    "fat": {"1004", "1085"},
    "carbohydrate": {"1005", "1050"},
}


def missing_basics(folder, fdc_ids) -> dict:
    """fdc_id -> list of COMPLETENESS keys the food has no value for."""
    path = Path(folder) / "food_nutrient.csv"
    wanted, have = set(fdc_ids), {}
    needed = set().union(*COMPLETENESS.values())
    for ch in pd.read_csv(path, dtype=str, usecols=["fdc_id", "nutrient_id", "amount"], chunksize=500_000):
        ch = ch[ch.fdc_id.isin(wanted) & ch.nutrient_id.isin(needed) & ch.amount.notna()]
        for f, n in zip(ch.fdc_id, ch.nutrient_id):
            have.setdefault(f, set()).add(n)
    return {f: [k for k, ids in COMPLETENESS.items() if not have.get(f, set()) & ids] for f in wanted}


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
    food = current_foods(a.fdc_dir)  # supported datasets, superseded Foundation versions hidden
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


def score_description(desc: str, term_words: list, term_segments: list = None):
    """None if the description does not match any term; else (score, match_label).

    term_segments[i] is how many comma-separated parts term i has: a term written the USDA way
    ("squash, summer, zucchini") is an exact match when it equals that many leading parts."""
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
    for i, tw in enumerate(term_words):
        if not tw or not all(w in have for w in tw):
            continue
        k = term_segments[i] if term_segments else 1
        if k > 2 and set(words(",".join(segments[:k]))) == set(tw):
            cand = (58, "exact")
        elif set(head) == set(tw):
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
    catalog = []
    for folder in a.fdc_dir:
        food = current_foods(folder)
        food = food[food.description.apply(lambda d: isinstance(d, str))]
        missing = missing_basics(folder, food.fdc_id)
        catalog += [{"fdc_id": r.fdc_id, "desc": r.description, "guess": guess_form(r.description),
                     "state": guess_state(r.description), "source": DATASETS[r.data_type],
                     "missing": missing.get(r.fdc_id, [])}
                    for r in food.itertuples()]

    wanted = pd.read_csv(a.wanted, dtype=str, encoding="utf-8-sig").fillna("")
    for col in ("food_name_ar", "food_name_en"):
        if col not in wanted.columns:
            sys.exit(f"Wanted list needs a '{col}' column")

    out = []
    for w in wanted.to_dict("records"):
        terms = [t.strip() for t in (w.get("search_terms") or w["food_name_en"]).split(";") if t.strip()]
        term_words = [words(t) for t in terms]
        term_segments = [len(t.split(",")) for t in terms]
        targets = [t.strip().lower() for t in (w.get("forms") or "any").split(";") if t.strip()] or ["any"]

        exclude = [t.strip().lower() for t in (w.get("exclude") or "").split(";") if t.strip()]

        scored = []
        for c in catalog:
            if any(has_word(c["desc"].lower(), x) for x in exclude):
                continue
            res = score_description(c["desc"], term_words, term_segments)
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
            if not fits:
                out.append({"pick": "", "hint": "", "food_name_ar": w["food_name_ar"],
                            "food_name_en": w["food_name_en"], "target_form": target, "food_form_code": "", "fdc_id": "",
                            "description": "NO CANDIDATE FOUND - search manually or add the data yourself",
                            "data_source": "", "missing": "",
                            "match": "", "form_note": "", "score": "", "rank": ""})
            # Each release is ranked on its own: the best Foundation item AND the best SR Legacy
            # item can both be picked. The import keeps them side by side for the same food and
            # form; recipes use the higher-priority one and borrow what it lacks from the other.
            for source in sorted({c["source"] for c in fits}, key=lambda x: list(DATASETS.values()).index(x)):
                group = sorted((c for c in fits if c["source"] == source), key=lambda c: (-c["score"], c["fdc_id"]))
                gap = (group[0]["score"] - group[1]["score"]) if len(group) > 1 else None
                for rank, c in enumerate(group[: a.top], start=1):
                    strong = c["match"] in ("exact", "alias")
                    clear = gap is None or gap >= a.min_gap
                    lacks_macros = [m for m in c["missing"] if m != "energy"]
                    confident = rank == 1 and strong and clear and not lacks_macros
                    hint = ""
                    if rank == 1 and not confident:
                        if not strong:
                            hint = "weak name match: check"
                        elif not clear:
                            hint = "several similar candidates: choose one"
                        else:
                            hint = "incomplete analysis: no " + ", ".join(lacks_macros)
                    out.append({"pick": "yes" if confident else "", "hint": hint, "food_name_ar": w["food_name_ar"],
                                "food_name_en": w["food_name_en"], "target_form": target,
                                "food_form_code": c["form_code"], "fdc_id": c["fdc_id"],
                                "description": c["desc"], "data_source": source,
                                "missing": ", ".join(c["missing"]),
                                "match": c["match"], "form_note": c["form_note"],
                                "score": c["score"], "rank": rank})

    df = pd.DataFrame(out)
    # build accepts one food per USDA item: never pre-mark the same fdc_id twice
    first = {}
    for i in df.index[df.pick == "yes"]:
        key = df.at[i, "fdc_id"]
        if key in first:
            df.at[i, "pick"] = ""
            df.at[i, "hint"] = f"same USDA item already picked for {first[key]}: choose another or drop this row"
        else:
            first[key] = f"{df.at[i, 'food_name_ar']} ({df.at[i, 'target_form']})"
    df.to_csv(a.out, index=False, encoding="utf-8-sig")  # BOM so Excel shows Arabic correctly
    pairs = df.groupby(["food_name_ar", "target_form"], sort=False)
    total = pairs.ngroups
    covered = int(pairs.pick.apply(lambda p: (p == "yes").any()).sum())
    empty = int(pairs.fdc_id.apply(lambda f: (f == "").all()).sum())
    print(f"draft: {len(df)} rows for {len(wanted)} foods -> {a.out}")
    print(f"  food/form pairs with a pre-marked pick: {covered} of {total} | you must choose: {total - covered - empty}"
          f" | no candidate at all: {empty}")
    for source, n in df[df.pick == "yes"].data_source.value_counts().items():
        print(f"  pre-marked from {source}: {n}")
    shaky = df[(df.pick == "yes") & (df.missing != "")]
    if not shaky.empty:
        print(f"  {len(shaky)} pick(s) lack energy only: it is computed from protein/fat/carbohydrate in recipes")



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
    dirs = a.fdc_dir

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
    wanted = set(cw.fdc_id)

    # Which release each picked fdc_id lives in (fdc ids are unique across FoodData Central).
    home, frames = {}, []
    for folder in dirs:
        f = read(folder, "food.csv", required=True, usecols=["fdc_id", "data_type", "description", "publication_date"])
        f = f[f.fdc_id.isin(wanted - set(home))]
        home.update({i: folder for i in f.fdc_id})
        frames.append(f)
    food = pd.concat(frames)
    missing = wanted - set(food.fdc_id)
    if missing:
        sys.exit(f"fdc_id not found in food.csv of {', '.join(dirs)}: {sorted(missing)}")

    # One item per food, form AND release: a Foundation and an SR Legacy pick for the same
    # food and form are both kept (recipes borrow missing nutrients between them).
    src = cw.fdc_id.map(dict(zip(food.fdc_id, food.data_type))).map(DATASETS).fillna("")
    same = cw.food_id.where(cw.food_id != "", cw.food_name_ar) + " | " + cw.food_form_code + " | " + src
    if same.duplicated().any():
        sys.exit("More than one USDA item of the same release is picked for the same food and form "
                 f"(choose only one): {sorted(set(same[same.duplicated(keep=False)]))}")

    # ---- records ----
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
    cols = ["fdc_id", "nutrient_id", "amount", "data_points", "derivation_id", "min", "max"]
    chunks, nmap, der_map = [], None, {}
    for folder in dirs:
        ids = {i for i, d in home.items() if d == folder}
        if not ids:
            continue
        m = load_nutrient_map(a, read(folder, "nutrient.csv", required=True))
        nmap = m if nmap is None else pd.concat([nmap, m]).drop_duplicates(["nutrient_code", "code"])
        path = Path(folder) / "food_nutrient.csv"
        header = pd.read_csv(path, nrows=0).columns
        chunks += [
            ch[ch.fdc_id.isin(ids) & ch.nutrient_id.isin(set(m.code))]
            for ch in pd.read_csv(path, dtype=str, usecols=[c for c in cols if c in header], chunksize=500_000)
        ]
        der = read(folder, "food_nutrient_derivation.csv")
        if der is not None:
            der = der.fillna("")
            der_map.update(zip(der.id, der.code + ": " + der.description))
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
    por_frames, mu_name = [], {}
    for folder in dirs:
        p = read(folder, "food_portion.csv")
        if p is not None:
            por_frames.append(p[p.fdc_id.isin({i for i, d in home.items() if d == folder})])
        mu = read(folder, "measure_unit.csv")
        if mu is not None:
            mu_name.update(zip(mu.id, mu.name))
    por = pd.concat(por_frames) if por_frames else None
    if por is not None and not por.empty:  # skip cleanly when none of the foods has portions
        por = por.copy()
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
    g.add_argument("--fdc-dir", required=True, action="append",
                   help="a release folder; repeat to search several (e.g. Foundation and SR Legacy)")
    g.add_argument("--wanted", required=True)
    g.add_argument("--out", default="draft_crosswalk.csv")
    g.add_argument("--top", type=int, default=3)
    g.add_argument("--min-gap", type=float, default=10.0,
                   help="pre-mark the best candidate only if it beats the runner-up by this many score points")
    g.set_defaults(fn=cmd_suggest)

    b = sub.add_parser("build")
    b.add_argument("--fdc-dir", required=True, action="append",
                   help="every release folder the crosswalk picks from; repeat for several")
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
