#!/usr/bin/env zsh
set -euo pipefail

# Expand this map as needed. Left = v1 string, Right = v2 string.
typeset -A MAP
MAP=(
  # Common ones you’ve hit
  heroicon-o-upload                     heroicon-o-arrow-up-tray
  heroicon-o-download                   heroicon-o-arrow-down-tray
  heroicon-o-collection                 heroicon-o-rectangle-stack
  heroicon-o-device-mobile              heroicon-o-device-phone-mobile
  heroicon-o-document-report            heroicon-o-document-chart-bar
  heroicon-o-adjustments                heroicon-o-adjustments-vertical
  heroicon-o-office-building            heroicon-o-building-office
  heroicon-o-cash                       heroicon-o-banknotes

  # Other frequent v1→v2 renames
  heroicon-o-external-link              heroicon-o-arrow-top-right-on-square
  heroicon-o-reply                      heroicon-o-arrow-uturn-left
  heroicon-o-reply-all                  heroicon-o-arrow-uturn-right
  heroicon-o-collection                 heroicon-o-rectangle-stack
  heroicon-o-duplicate                  heroicon-o-square-2-stack
  heroicon-o-dots-horizontal            heroicon-o-ellipsis-horizontal
  heroicon-o-dots-vertical              heroicon-o-ellipsis-vertical
  heroicon-o-adjustments-horizontal     heroicon-o-funnel
  heroicon-o-clipboard-check            heroicon-o-clipboard-document-check
  heroicon-o-clipboard                  heroicon-o-clipboard-document
  heroicon-o-clipboard-list             heroicon-o-clipboard-document-list
  heroicon-o-cloud-download             heroicon-o-cloud-arrow-down
  heroicon-o-cloud-upload               heroicon-o-cloud-arrow-up
  heroicon-o-code                       heroicon-o-code-bracket
  heroicon-o-database                   heroicon-o-circle-stack
  heroicon-o-desktop-computer           heroicon-o-computer-desktop
  heroicon-o-document-add               heroicon-o-document-plus
  heroicon-o-document-download          heroicon-o-document-arrow-down
  heroicon-o-document-search            heroicon-o-document-magnifying-glass
  heroicon-o-download                   heroicon-o-arrow-down-tray
  heroicon-o-upload                     heroicon-o-arrow-up-tray
  heroicon-o-exclamation                heroicon-o-exclamation-triangle
  heroicon-o-inbox-in                   heroicon-o-inbox-arrow-down
  heroicon-o-language                   heroicon-o-language
  heroicon-o-mail                       heroicon-o-envelope
  heroicon-o-mail-open                  heroicon-o-envelope-open
  heroicon-o-menu-alt-2                 heroicon-o-bars-3-center-left
  heroicon-o-menu-alt-3                 heroicon-o-bars-3-bottom-left
  heroicon-o-menu-alt-4                 heroicon-o-bars-3-bottom-right
  heroicon-o-menu                       heroicon-o-bars-3
  heroicon-o-microphone                 heroicon-o-megaphone
  heroicon-o-minus-circle               heroicon-o-no-symbol
  heroicon-o-phone-incoming             heroicon-o-phone-arrow-down-left
  heroicon-o-phone-outgoing             heroicon-o-phone-arrow-up-right
  heroicon-o-photograph                 heroicon-o-photo
  heroicon-o-qrcode                     heroicon-o-qr-code
  heroicon-o-refresh                    heroicon-o-arrow-path
  heroicon-o-selector                   heroicon-o-chevron-up-down
  heroicon-o-share                      heroicon-o-arrow-up-on-square
  heroicon-o-view-boards                heroicon-o-rectangle-group
  heroicon-o-view-grid                  heroicon-o-squares-2x2
  heroicon-o-view-grid-add              heroicon-o-squares-plus
  heroicon-o-volume-off                 heroicon-o-speaker-x-mark
  heroicon-o-volume-up                  heroicon-o-speaker-wave
)

# Files to scan
find app resources config -type f \( \
  -name "*.php" -o -name "*.blade.php" -o -name "*.js" -o -name "*.ts" -o -name "*.vue" \
\) -print0 | while IFS= read -r -d '' f; do
  for old new in "${(@kv)MAP}"; do
    # macOS sed requires -i '' ; on Linux use: sed -i -e ...
    sed -i '' -e "s/${old}/${new}/g" "$f"
  done
done

echo "Done. Clearing caches…"
php artisan icons:clear || true
php artisan view:clear || true
php artisan optimize:clear || true
