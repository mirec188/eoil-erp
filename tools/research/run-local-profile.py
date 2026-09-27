#!/usr/bin/env python3
"""Local research only. Credentials are read from a protected file outside Git."""
import argparse, json, os, pathlib, subprocess
parser=argparse.ArgumentParser()
parser.add_argument('--database',choices=['DATA0001','DATA0002','DATA0003','DATASPOL','DATALOCK'],required=True)
parser.add_argument('--output',required=True)
args=parser.parse_args()
root=pathlib.Path(__file__).resolve().parents[2]
password=next(x.split('=',1)[1] for x in pathlib.Path('/Users/mirec/mrp-crossover-local/firebird.env').read_text().splitlines() if x.startswith('FIREBIRD_ROOT_PASSWORD='))
env=dict(os.environ,MRP_PASSWORD=password,MRP_DSN=f'firebird:dbname=localhost:/var/lib/firebird/data/research-20260927/{args.database}.MRP;charset=UTF8')
cmd=['docker','run','--rm','--network','container:mrp-crossover-fb','-e','MRP_PASSWORD','-e','MRP_DSN','-v',f'{root}/tools/research:/research:ro','eoil-php-fb:8.2','/research/firebird-profile.php']
r=subprocess.run(cmd,env=env,capture_output=True,text=True)
if r.returncode:
    raise SystemExit((r.stderr+r.stdout).replace(password,"[REDACTED]"))
obj=json.loads(r.stdout)
pathlib.Path(args.output).write_text(json.dumps(obj,ensure_ascii=False,indent=2)+'\n')
print(args.database, len(obj['tables']), 'tables;',sum(t['ROW_COUNT']>0 for t in obj['tables']),'nonempty')
