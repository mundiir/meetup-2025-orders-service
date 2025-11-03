#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

if command -v plantuml >/dev/null 2>&1; then
  echo "Rendering PUML -> SVG with plantuml"
  plantuml -tsvg ./*.puml
else
  echo "plantuml not found. Install it (e.g. brew install plantuml or apt-get install plantuml) to render SVGs."
  echo "PUML sources are ready."
fi

