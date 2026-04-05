#!/usr/bin/env python3
"""
CSS Design-Token Audit — flags any var(--vb-*) reference in admin.css
that does not resolve to a token defined in the :root block.

Intentionally excluded:
  - Tailwind internal tokens (--tw-*, --color-*, etc.)
  - Dynamic CSS custom properties set at runtime (--block-color, --pill-color, etc.)
  - Brand tokens injected per-tenant (--vb-brand*)

Usage:
    python3 scripts/audit-css-tokens.py
    Returns exit code 0 if clean, 1 if broken tokens found.
"""
import re, sys, os

CSS_PATH = os.path.join(os.path.dirname(__file__), '..', 'resources', 'css', 'admin.css')

# Tokens set dynamically at runtime, not in :root
DYNAMIC_TOKENS = {
    '--block-color', '--pill-color', '--brand-color', '--tenant-brand',
    '--vb-brand', '--vb-brand-hover', '--vb-brand-light', '--vb-brand-text',
    '--vb-body-weight',
}

# Prefixes owned by Tailwind CSS / browser internals — skip these
SKIP_PREFIXES = (
    '--tw-', '--color-', '--animate-', '--default-', '--spacing',
    '--container', '--breakpoint', '--font-', '--blur', '--ring',
    '--inset-', '--radius', '--shadow', '--alpha',
    '--_',    # CSS-native private scoped properties (internal calc helpers)
    '--col-', # Inline booking column properties set by template
)


def main():
    with open(CSS_PATH) as f:
        css = f.read()

    # 1. Collect all tokens defined in :root { ... }
    defined = set()
    for block in re.finditer(r'(:root|\[data-theme="dark"\])\s*\{([^}]+)\}', css):
        for m in re.finditer(r'(--[\w-]+)\s*:', block.group(2)):
            defined.add(m.group(1))

    # 2. Collect all var() usages with line numbers
    usages: dict[str, list[int]] = {}
    for i, line in enumerate(css.split('\n'), 1):
        for m in re.finditer(r'var\((--[\w-]+)', line):
            token = m.group(1)
            usages.setdefault(token, []).append(i)

    # 3. Find undefined
    undefined = {}
    for token, lines in sorted(usages.items()):
        if token in defined or token in DYNAMIC_TOKENS:
            continue
        if any(token.startswith(p) for p in SKIP_PREFIXES):
            continue
        undefined[token] = lines

    # 4. Report
    if undefined:
        print(f'FAIL: {len(undefined)} undefined CSS token(s) in admin.css:\n')
        for token, lines in sorted(undefined.items()):
            locs = ', '.join(str(l) for l in lines[:5])
            suffix = f' (+{len(lines)-5} more)' if len(lines) > 5 else ''
            print(f'  {token}  →  lines {locs}{suffix}')
        print(f'\nDefined tokens: {len(defined)}  |  Used: {len(usages)}  |  Broken: {len(undefined)}')
        sys.exit(1)
    else:
        print(f'✓ 0 undefined CSS tokens. {len(defined)} defined, {len(usages)} used.')
        sys.exit(0)


if __name__ == '__main__':
    main()
