"""Parse every admin inline script with PHP interpolation replaced by a scalar."""
from pathlib import Path
import re, subprocess
root = Path(__file__).resolve().parents[1]
count = 0
for path in (root / 'admin').rglob('*.php'):
    text = path.read_text(encoding='utf-8-sig')
    if '\ufffd' in text or any(ord(c) < 32 and c not in '\t\r\n' for c in text):
        raise SystemExit(f'Invalid encoding/control character: {path}')
    rendered = re.sub(r'<\?(?:php|=).*?\?>', '0', text, flags=re.S)
    for script in re.findall(r'<script\b[^>]*>(.*?)</script>', rendered, re.S | re.I):
        if not script.strip():
            continue
        code = re.sub(r'<\?(?:php|=).*?\?>', '0', script, flags=re.S)
        result = subprocess.run(['node', '--check'], input=code, text=True, encoding='utf-8', capture_output=True, timeout=15)
        if result.returncode:
            raise SystemExit(f'{path}: {result.stderr}')
        count += 1
print(f'{count} inline admin scripts syntax and UTF-8/control characters OK')
