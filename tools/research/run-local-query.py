#!/usr/bin/env python3
import argparse,json,os,pathlib,subprocess
p=argparse.ArgumentParser();p.add_argument('queries');p.add_argument('output');p.add_argument('--database',default='DATA0003',choices=['DATA0001','DATA0002','DATA0003','DATASPOL','DATALOCK']);a=p.parse_args()
root=pathlib.Path(__file__).resolve().parents[2]
pw=next(l.split('=',1)[1] for l in pathlib.Path('/Users/mirec/mrp-crossover-local/firebird.env').read_text().splitlines() if l.startswith('FIREBIRD_ROOT_PASSWORD='))
query=pathlib.Path(a.queries).resolve()
env=dict(os.environ,MRP_PASSWORD=pw,MRP_DSN=f'firebird:dbname=localhost:/var/lib/firebird/data/research-20260927/{a.database}.MRP;charset=UTF8')
r=subprocess.run(['docker','run','--rm','--network','container:mrp-crossover-fb','-e','MRP_PASSWORD','-e','MRP_DSN','-v',f'{root}/tools/research:/research:ro','-v',f'{query}:/queries.json:ro','eoil-php-fb:8.2','/research/firebird-query.php','/queries.json'],env=env,capture_output=True,text=True)
if r.returncode:raise SystemExit((r.stdout+r.stderr).replace(pw,'[REDACTED]'))
d=json.loads(r.stdout);pathlib.Path(a.output).write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
errors=[k for k,v in d.items() if 'error' in v];print(len(d),'queries;', 'errors:',errors)

if errors: raise SystemExit(1)
