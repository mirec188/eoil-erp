#!/usr/bin/env python3
"""Publish reviewed aggregate evidence, never database rows or procedure bodies."""
import csv
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PRIVATE = ROOT / '.local'
OUT = ROOT / 'docs/research/evidence'
OUT.mkdir(parents=True, exist_ok=True)
DATABASES = ['DATA0001', 'DATA0002', 'DATA0003', 'DATASPOL', 'DATALOCK']
profiles = {name: json.loads((PRIVATE / f'{name}.json').read_text()) for name in DATABASES}
main = profiles['DATA0003']

def write_csv(name, rows):
    if not rows:
        return
    with (OUT / name).open('w', newline='') as stream:
        writer = csv.DictWriter(stream, fieldnames=list(rows[0]), lineterminator='\n')
        writer.writeheader()
        writer.writerows(rows)

def domain(table):
    # Navigation hints based on names, not a claim of confirmed business semantics.
    for label, prefixes in [
        ('fiscal-pos', ('EKASA', 'MO')),
        ('inventory', ('SK',)),
        ('invoicing', ('FAK',)),
        ('orders-quotes', ('OBJ', 'NAB')),
        ('partners', ('ADRES',)),
        ('cash-accounting', ('PEN', 'POKL', 'UC', 'BAN', 'ZAP', 'OSPOH', 'OSZAV', 'INTDOK')),
        ('attachments-communication', ('DOC', 'EMAIL')),
        ('access-configuration', ('USR', 'FIR', 'NAST', 'SPOL', 'LOCK')),
        ('assets-payroll', ('MAJET', 'MX', 'MZ')),
        ('vehicles', ('AUTA', 'JAZD')),
    ]:
        if table.startswith(prefixes):
            return label
    return 'unclassified-review-required'

counts = {db: {t['TABLE_NAME']: t['ROW_COUNT'] for t in p['tables']} for db, p in profiles.items()}
tables = sorted(set().union(*(set(v) for v in counts.values())))
write_csv('table-inventory.csv', [dict(
    table=t, domain_hint=domain(t), semantic_review='pending-unless-covered-in-report',
    **{db: counts[db].get(t, 'ABSENT') for db in DATABASES}) for t in tables])
for key in ['columns', 'keys', 'foreign_keys', 'objects']:
    write_csv(f'DATA0003-{key}.csv', main[key])

# Only document/stock dates; exclude personal, authentication and configuration fields.
business_tables = {'SKPOH', 'FAKVY', 'FAKPR', 'OBJPR', 'OBJVY', 'MOARCHIV',
                   'MOUZAVERKY', 'SKKARINV', 'PENDE', 'POKL', 'OSPOH', 'ZAPOCTY',
                   'EMAILLST', 'EKASA_LOG', 'DOCSTORE', 'FIRLOG', 'DOCEVENT'}
write_csv('business-date-ranges.csv', [dict(database=db, table=t['TABLE_NAME'], column=column, **span)
    for db, p in profiles.items() for t in p['tables'] if t['TABLE_NAME'] in business_tables
    for column, span in (t['DATES'] or {}).items()])

for name in ['usage', 'deep']:
    data = json.loads((PRIVATE / f'{name}.json').read_text())
    assert all('error' not in result for result in data.values()), f'{name}: query errors'
    # These query sets are manually reviewed aggregates, enums and metadata.
    (OUT / f'{name}.json').write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')

logic = json.loads((PRIVATE / 'logic.json').read_text())
write_csv('reviewed-logic.csv', [dict(kind=kind, name=row['NAME'],
    sha256=hashlib.sha256(row['SOURCE'].encode()).hexdigest())
    for kind, result in logic.items() for row in result['rows']])
sources = json.loads(Path('/Users/mirec/mrp-crossover-local/source-manifest.json').read_text())
for name, info in sources.items():
    digest = hashlib.sha256()
    with Path(info['source']).open('rb') as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b''):
            digest.update(chunk)
    assert digest.hexdigest() == info['sha256'], f'Original changed: {name}'
manifest = {
    'scope': 'Local supplied copy, not live production. No consistency certification of original copy.',
    'research_snapshot': 'research-20260927; all five databases enforced read-only',
    'source_files': {name: {k: v for k, v in info.items() if k != 'source'} for name, info in sources.items()},
    'profiles': {db: dict(captured_at=p['captured_at'], captured_end=p['captured_end'],
        metadata=p['database'], tables=len(p['tables']), populated_tables=sum(t['ROW_COUNT'] > 0 for t in p['tables']))
        for db, p in profiles.items()},
    'eoil_code_revision': 'de5a5c2241503a02191a8d230078af7e071fac0b',
    'eoil_data_profiled': False,
    'source_file_hashes_rechecked': True,
}
(OUT / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
print(f'Exported {len(tables)} table names; aggregate and schema evidence only.')
