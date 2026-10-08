# VixCRM import (separate source)

`bin/import-vixcrm.php` accepts a complete Realting XML snapshot from VixCRM.
The feed URL belongs in the private configuration outside `public_html`; never
put the URL or XML in Git. `--sync` downloads a temporary XML file using HTTPS
from the allowlisted VixCRM endpoint and removes it after the run. The importer
has no web entry point.

1. Run `php bin/import-vixcrm.php --check /absolute/feed.xml`. This validates
   every offer and prints the offer and photo counts without database writes.
2. Set `vixcrm_import_enabled` in the private `config.php` only after reviewing
   the dry run. Keep `vixcrm_publish_mode` at `none` until publication is approved.
3. Run `php bin/import-vixcrm.php --import /absolute/feed.xml` on the hosting
   account. The import is a transaction scoped to `source='vixcrm'`. A complete
   snapshot removes VixCRM rows absent from that snapshot; MLS and EstiCRM rows
   are untouched. Fewer than 200 valid offers or a snapshot that shrank by more
   than 30% aborts the operation before changing offers.
4. Check the VixCRM row count and a sample of IDs (`vix-O-...`) before enabling
   publication. A later import applies `vixcrm_publish_mode`: `all` publishes
   the full feed, while `selected` publishes only `vixcrm_publish_ids`.

The current feed has 339 offers and 3,816 photo links. Photos remain on the
VixCRM host; they are not copied to the site. This avoids several gigabytes of
storage but means photo availability depends on that host. The single `type=22`
record is mapped as an apartment based on its 98.03 m² area and four rooms;
confirm its placement before publication. The feed also includes locations outside Mazowieckie, so any region
restriction for this source requires an explicit product decision.

Descriptions retain their original text and use the shared MLS/EstiCRM
description document and frontend styles. Recognizable headings and consecutive
bullets become headings and lists; ordinary prose retains paragraphs. Formatting
does not translate or rewrite facts.

No recurring cron is installed by this change. Once the first import is verified
and scheduling is approved, schedule a private `php bin/import-vixcrm.php --sync`
run. Do not enable automatic publication before approval.
