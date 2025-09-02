#!/usr/bin/env bash
set -euo pipefail

# --- Where to scan ---
SCAN_DIRS=("app" "resources" "config")

echo "Step 1/3: Cleaning up any accidental double replacements…"
# Pre-clean any 'vertical-vertical' mistakes (safe to run even if none exist)
FOUND_FILES=$(grep -RIl "adjustments-vertical-vertical" "${SCAN_DIRS[@]}" 2>/dev/null || true)
if [[ -n "${FOUND_FILES}" ]]; then
  echo "${FOUND_FILES}" | xargs -I{} perl -pi -e \
    's/heroicon-o-adjustments-vertical-vertical/heroicon-o-adjustments-vertical/g; s/heroicon-s-adjustments-vertical-vertical/heroicon-s-adjustments-vertical/g' "{}"
else
  echo "  No double-replaced tokens found."
fi

echo "Step 2/3: Running boundary-safe v1→v2 migration…"

# Create a temporary Perl migrator (boundary-safe, ordered replacements)
TMP_PERL="$(mktemp -t migrate_heroicons_XXXXXX.pl)"
cat > "${TMP_PERL}" <<'PERL'
#!/usr/bin/env perl
use strict;
use warnings;

# Ordered v1 -> v2 mappings (specific first, then generic).
my @map = (
  # Guard against leftovers
  ['heroicon-o-adjustments-vertical-vertical', 'heroicon-o-adjustments-vertical'],
  ['heroicon-s-adjustments-vertical-vertical', 'heroicon-s-adjustments-vertical'],

  # Specific renames first
  ['heroicon-o-adjustments-horizontal', 'heroicon-o-funnel'],
  ['heroicon-s-adjustments-horizontal', 'heroicon-s-funnel'],

  # Common v1 -> v2 Blade string renames
  ['heroicon-o-upload',               'heroicon-o-arrow-up-tray'],
  ['heroicon-s-upload',               'heroicon-s-arrow-up-tray'],
  ['heroicon-o-download',             'heroicon-o-arrow-down-tray'],
  ['heroicon-s-download',             'heroicon-s-arrow-down-tray'],

  ['heroicon-o-collection',           'heroicon-o-rectangle-stack'],
  ['heroicon-s-collection',           'heroicon-s-rectangle-stack'],

  ['heroicon-o-device-mobile',        'heroicon-o-device-phone-mobile'],
  ['heroicon-s-device-mobile',        'heroicon-s-device-phone-mobile'],

  ['heroicon-o-document-report',      'heroicon-o-document-chart-bar'],
  ['heroicon-s-document-report',      'heroicon-s-document-chart-bar'],

  ['heroicon-o-adjustments',          'heroicon-o-adjustments-vertical'],
  ['heroicon-s-adjustments',          'heroicon-s-adjustments-vertical'],

  ['heroicon-o-office-building',      'heroicon-o-building-office'],
  ['heroicon-s-office-building',      'heroicon-s-building-office'],

  ['heroicon-o-cash',                 'heroicon-o-banknotes'],
  ['heroicon-s-cash',                 'heroicon-s-banknotes'],

  ['heroicon-o-external-link',        'heroicon-o-arrow-top-right-on-square'],
  ['heroicon-s-external-link',        'heroicon-s-arrow-top-right-on-square'],

  ['heroicon-o-reply',                'heroicon-o-arrow-uturn-left'],
  ['heroicon-s-reply',                'heroicon-s-arrow-uturn-left'],
  ['heroicon-o-reply-all',            'heroicon-o-arrow-uturn-right'],
  ['heroicon-s-reply-all',            'heroicon-s-arrow-uturn-right'],

  ['heroicon-o-duplicate',            'heroicon-o-square-2-stack'],
  ['heroicon-s-duplicate',            'heroicon-s-square-2-stack'],

  ['heroicon-o-dots-horizontal',      'heroicon-o-ellipsis-horizontal'],
  ['heroicon-s-dots-horizontal',      'heroicon-s-ellipsis-horizontal'],
  ['heroicon-o-dots-vertical',        'heroicon-o-ellipsis-vertical'],
  ['heroicon-s-dots-vertical',        'heroicon-s-ellipsis-vertical'],

  ['heroicon-o-clipboard-check',      'heroicon-o-clipboard-document-check'],
  ['heroicon-s-clipboard-check',      'heroicon-s-clipboard-document-check'],
  ['heroicon-o-clipboard-list',       'heroicon-o-clipboard-document-list'],
  ['heroicon-s-clipboard-list',       'heroicon-s-clipboard-document-list'],
  ['heroicon-o-clipboard',            'heroicon-o-clipboard-document'],
  ['heroicon-s-clipboard',            'heroicon-s-clipboard-document'],

  ['heroicon-o-cloud-download',       'heroicon-o-cloud-arrow-down'],
  ['heroicon-s-cloud-download',       'heroicon-s-cloud-arrow-down'],
  ['heroicon-o-cloud-upload',         'heroicon-o-cloud-arrow-up'],
  ['heroicon-s-cloud-upload',         'heroicon-s-cloud-arrow-up'],

  ['heroicon-o-code',                 'heroicon-o-code-bracket'],
  ['heroicon-s-code',                 'heroicon-s-code-bracket'],

  ['heroicon-o-database',             'heroicon-o-circle-stack'],
  ['heroicon-s-database',             'heroicon-s-circle-stack'],

  ['heroicon-o-desktop-computer',     'heroicon-o-computer-desktop'],
  ['heroicon-s-desktop-computer',     'heroicon-s-computer-desktop'],

  ['heroicon-o-document-add',         'heroicon-o-document-plus'],
  ['heroicon-s-document-add',         'heroicon-s-document-plus'],
  ['heroicon-o-document-download',    'heroicon-o-document-arrow-down'],
  ['heroicon-s-document-download',    'heroicon-s-document-arrow-down'],
  ['heroicon-o-document-search',      'heroicon-o-document-magnifying-glass'],
  ['heroicon-s-document-search',      'heroicon-s-document-magnifying-glass'],

  ['heroicon-o-exclamation',          'heroicon-o-exclamation-triangle'],
  ['heroicon-s-exclamation',          'heroicon-s-exclamation-triangle'],

  ['heroicon-o-inbox-in',             'heroicon-o-inbox-arrow-down'],
  ['heroicon-s-inbox-in',             'heroicon-s-inbox-arrow-down'],

  ['heroicon-o-mail-open',            'heroicon-o-envelope-open'],
  ['heroicon-s-mail-open',            'heroicon-s-envelope-open'],
  ['heroicon-o-mail',                 'heroicon-o-envelope'],
  ['heroicon-s-mail',                 'heroicon-s-envelope'],

  ['heroicon-o-menu-alt-2',           'heroicon-o-bars-3-center-left'],
  ['heroicon-s-menu-alt-2',           'heroicon-s-bars-3-center-left'],
  ['heroicon-o-menu-alt-3',           'heroicon-o-bars-3-bottom-left'],
  ['heroicon-s-menu-alt-3',           'heroicon-s-bars-3-bottom-left'],
  ['heroicon-o-menu-alt-4',           'heroicon-o-bars-3-bottom-right'],
  ['heroicon-s-menu-alt-4',           'heroicon-s-bars-3-bottom-right'],
  ['heroicon-o-menu',                 'heroicon-o-bars-3'],
  ['heroicon-s-menu',                 'heroicon-s-bars-3'],

  ['heroicon-o-microphone',           'heroicon-o-megaphone'],
  ['heroicon-s-microphone',           'heroicon-s-megaphone'],

  ['heroicon-o-minus-circle',         'heroicon-o-no-symbol'],
  ['heroicon-s-minus-circle',         'heroicon-s-no-symbol'],

  ['heroicon-o-phone-incoming',       'heroicon-o-phone-arrow-down-left'],
  ['heroicon-s-phone-incoming',       'heroicon-s-phone-arrow-down-left'],
  ['heroicon-o-phone-outgoing',       'heroicon-o-phone-arrow-up-right'],
  ['heroicon-s-phone-outgoing',       'heroicon-s-phone-arrow-up-right'],

  ['heroicon-o-photograph',           'heroicon-o-photo'],
  ['heroicon-s-photograph',           'heroicon-s-photo'],

  ['heroicon-o-qrcode',               'heroicon-o-qr-code'],
  ['heroicon-s-qrcode',               'heroicon-s-qr-code'],

  ['heroicon-o-refresh',              'heroicon-o-arrow-path'],
  ['heroicon-s-refresh',              'heroicon-s-arrow-path'],

  ['heroicon-o-selector',             'heroicon-o-chevron-up-down'],
  ['heroicon-s-selector',             'heroicon-s-chevron-up-down'],

  ['heroicon-o-view-boards',          'heroicon-o-rectangle-group'],
  ['heroicon-s-view-boards',          'heroicon-s-rectangle-group'],
  ['heroicon-o-view-grid-add',        'heroicon-o-squares-plus'],
  ['heroicon-s-view-grid-add',        'heroicon-s-squares-plus'],
  ['heroicon-o-view-grid',            'heroicon-o-squares-2x2'],
  ['heroicon-s-view-grid',            'heroicon-s-squares-2x2'],
);

# Compile boundary-aware regex for each mapping
my @compiled = map {
  my ($old, $new) = @$_;
  my $re = qr/(?<![a-z-])\Q$old\E(?![a-z-])/; # avoid partial tokens
  [$re, $new];
} @map;

# Process each file passed on the command line
while (my $file = shift @ARGV) {
  next unless -f $file;
  local @ARGV = ($file);
  local $^I = '.bak';  # inline edit, keep backup
  while (<>) {
    for my $pair (@compiled) {
      my ($re, $new) = @$pair;
      s/$re/$new/g;
    }
    print;
  }
}
PERL
chmod +x "${TMP_PERL}"

# Collect files (portable: no mapfile)
TARGETS=()
while IFS= read -r file; do
  TARGETS+=("$file")
done < <(find "${SCAN_DIRS[@]}" -type f \( -name "*.php" -o -name "*.blade.php" -o -name "*.js" -o -name "*.ts" -o -name "*.vue" \))

if [[ ${#TARGETS[@]} -eq 0 ]]; then
  echo "  No target files found. Exiting."
  rm -f "${TMP_PERL}"
  exit 0
fi

# Run the migrator
"${TMP_PERL}" "${TARGETS[@]}"

# Remove .bak backups (comment out if you want to inspect first)
find "${SCAN_DIRS[@]}" -type f -name "*.bak" -delete || true
rm -f "${TMP_PERL}"

echo "Step 3/3: Clearing Laravel caches…"
php artisan icons:clear || true
php artisan view:clear  || true
php artisan optimize:clear || true

echo "All done ✅"
echo "Tip: audit remaining heroicon strings:"
echo "  grep -RIn \"heroicon-[os]-\" ${SCAN_DIRS[*]} 2>/dev/null | cut -d: -f1 | sort -u"
