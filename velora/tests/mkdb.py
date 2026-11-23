import os, re, sqlite3, sys

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
FILES = ['database.sql']


def statements(text):
    buf = ''
    for line in text.splitlines():
        if line.strip().startswith('--'):
            continue
        buf += line + '\n'
        if sqlite3.complete_statement(buf):
            yield buf.strip()
            buf = ''
    if buf.strip():
        yield buf.strip()


def create_table(stmt):
    m = re.match(r'CREATE TABLE `(\w+)` \((.*)\)\s*ENGINE=[^;]*;?$', stmt, re.S)
    table, body = m.group(1), m.group(2)
    cols, indexes = [], []
    for line in body.split('\n'):
        line = line.strip().rstrip(',')
        if not line:
            continue
        if line.startswith('CONSTRAINT'):
            continue
        if line.startswith('PRIMARY KEY'):
            continue
        k = re.match(r'(UNIQUE )?KEY `(\w+)` \((.*)\)$', line)
        if k:
            indexes.append('CREATE %sINDEX `%s_%s` ON `%s` (%s)' % (k.group(1) or '', table, k.group(2), table, k.group(3)))
            continue
        if 'AUTO_INCREMENT' in line:
            name = re.match(r'`(\w+)`', line).group(1)
            line = '`%s` INTEGER PRIMARY KEY AUTOINCREMENT' % name
        else:
            line = re.sub(r'\s+(COLLATE|CHARACTER SET)\s+\w+', '', line)
        cols.append(line)
    pk = re.search(r'PRIMARY KEY \((.*?)\)', body)
    if pk and 'AUTOINCREMENT' not in ' '.join(cols):
        cols.append('PRIMARY KEY (%s)' % pk.group(1))
    if table == 'vb_orders':
        cols = [c.replace('`user_id` int NOT NULL', '`user_id` int NULL') for c in cols]
    return ['CREATE TABLE `%s` (\n  %s\n)' % (table, ',\n  '.join(cols))] + indexes


path = sys.argv[1]
if os.path.exists(path):
    os.remove(path)
con = sqlite3.connect(path)
for name in FILES:
    for stmt in statements(open(os.path.join(ROOT, name), encoding='utf-8').read()):
        head = stmt.upper()
        if head.startswith(('SET ', 'CREATE DATABASE', 'USE ')):
            continue
        parts = create_table(stmt) if head.startswith('CREATE TABLE') else [stmt.replace("\\'", "''").replace('NOW()', 'CURRENT_TIMESTAMP')]
        for out in parts:
            try:
                con.execute(out)
            except sqlite3.Error as err:
                print('FAILED in', name, ':', err)
                print(out[:300])
                sys.exit(1)
con.commit()
print(con.execute('select count(*) from vb_products').fetchone()[0], 'products;', con.execute('select count(*) from vb_users').fetchone()[0], 'users')
