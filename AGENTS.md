# AGENTS.md

Guidance for an LLM/agent using the `kv` CLI (read-only Redis inspection).
Every command below is non-interactive-capable: pass all flags and no
prompts fire.

Binary: `kv` (or `php kv` from a source checkout).

## Common mistake: connection is NOT a flag

`connection` is always the **first positional argument**:
`kv COMMAND CONNECTION [PATTERN|KEY|SECTION] [--options]`.

```bash
kv get --connection=cache mykey   # WRONG: there is no --connection option
kv get cache mykey                # RIGHT
```

## Connection profiles

Profiles live in `~/.kv-cli/config.json` (mode `0600`), keyed by name.

```bash
# Non-interactive add (pass every flag or a prompt fires; --db is optional, defaults to 0)
kv connections:add NAME --host=127.0.0.1 --port=6379 --password=PASS --no-interaction
kv connections:add NAME --host=HOST --port=6380 --db=2 --username=ACLUSER --password=PASS --tls --no-interaction

kv connections:list
kv connections:remove NAME --force
```

## Commands

```bash
kv keys NAME [PATTERN] [--db=N] [--limit=100] [--format=table|json|csv]
kv get NAME KEY [--db=N] [--limit=100] [--format=table|json|csv]
kv info NAME [SECTION] [--format=table|json|csv]
```

- `keys`: SCAN for keys matching a glob (default `*`), with type and TTL
  (`-1` = no expiry). Stops at `--limit`.
- `get`: reads any type. JSON shape:
  `{"key", "type", "ttl", "size", "rows": [...]}`. Row shape depends on type:
  string `{value}`, hash `{field, value}`, list `{index, value}`,
  set `{member}`, zset `{member, score}`, stream `{id, fields}`.
  Collections are capped at `--limit` elements; compare `size` against the
  row count to know whether output was truncated.
- `info`: rows of `{section, key, value}`. Useful sections: `server`,
  `memory`, `keyspace`, `stats`, `clients`.
- `--db` overrides the profile's database index for one call.

Read-only: no command writes, deletes or runs arbitrary Redis commands.

## Output format

Always pass `--format=json` when consuming output programmatically.

## Exit codes

`0` success, `1` failure (unknown connection, key not found on `get`,
connection/auth error).

## Example: end-to-end from a fresh agent

```bash
kv connections:add prod --host=10.0.0.5 --port=6379 --password="$REDIS_PASSWORD" --no-interaction
kv info prod keyspace --format=json
kv keys prod "laravel_cache:*" --limit=20 --format=json
kv get prod laravel_cache:settings --format=json
kv connections:remove prod --force
```
