# phpMyAdmin Designer layout

The `bloodmatch` default Designer page (page 1) stores positions for all 34 current tables, grouped into six lanes. `database/layout_phpmyadmin_designer.php` previews the arrangement; `--apply` saves it and backs up existing layout metadata. Application records and schema are not changed.

The compact revision measures Arial text at phpMyAdmin's default font size, uses each table's own width, leaves 78 pixels between lanes and 38 pixels between expanded table boxes, and aligns account/chat dependents toward members. These gaps leave more room for relationship lines. The estimated canvas is 3082 × 1719 pixels. Members → requests/matches → donation completion is the central flow, with account/chat tables to the left and delivery tables to the right. All 34 tables remain expanded.

The initial saved layout did not load because phpMyAdmin's configured `pma` control account had no privileges on its configuration storage. Designer returned `displayPage: -1` and `tablesEnabled: false`, falling back to an unsaved browser layout.

Resolved by granting `pma`@`localhost` SELECT, INSERT, UPDATE, DELETE on only `phpmyadmin.pma__pdf_pages` and `phpmyadmin.pma__table_coords`. No application database permissions were granted. A fresh HTTP response now returns `displayPage: 1`, `tablesEnabled: true`, and 34 expanded table elements with the saved coordinates.

Open `http://localhost/phpmyadmin/index.php?route=/database/designer&db=bloodmatch&page=1` to select the saved layout explicitly. Refresh an already-open Designer tab to load the corrected storage state. Browser rendering was not visually inspected because this session had no connected browser tools.
