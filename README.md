# nawasara/hibah

Recording of regional aid for the [Nawasara](https://github.com/nawasara) superapp: grants (hibah), social assistance (bansos), and financial assistance (bantuan keuangan) from the Ponorogo Regency APBD.

What is recorded here is proposals that have already been approved. The decision is made in a meeting, outside the system, and the app records the outcome. Because of that there is no approve/reject flow. What still changes after approval is the disbursement.

## Status v0.3.0

| Feature | Status |
|---|---|
| Three separate menus (Hibah / Bansos / Bantuan Keuangan) | ready |
| Recipient rules per purpose x form | ready |
| Disbursement status computed from realization | ready |
| Realization per quarter | ready |
| Monev evidence | ready |
| Recipient master + Recipient page across purposes | ready (v0.3.0) |
| Duplicate recipient detection (hibah & bansos) | ready |
| Report per purpose + Excel export | ready |
| Excel import + template | ready |
| Per-OPD scope (`nawasara/registry`) | ready |
| BK sub-type: PD | not built yet. The value exists in the database but is not offered yet, see the Notes section |

> v0.2.0 broke compatibility. Table names, columns, classes, routes, and permissions all changed, and the migration deletes v0.1.x data. Export a CSV backup before running it.

## Design notes

### 1. Three menu sections over one table

Hibah, bansos, and bantuan keuangan share one table, distinguished by the `purpose` column. The menus are still kept separate because hibah staff and bansos staff are different people, with different files and different supervisors. One table is a storage decision; there is no reason for it to leak onto their screens.

All three are sections within one workspace, not three separate workspaces. See the Menu section for why.

`purpose` is a path segment, not a query string: `hibah/bansos/uang`, not `?purpose=bansos`. `WorkspaceManager::current()` matches `request()->path()` and the sidebar uses `url()->current()`; both drop the query string, so with a query param all three menus would light up at once without a single line being wrong by the code's own logic.

### 2. Valid recipients are set by two axes, not one

```
Hibah  · Uang/Barang  → Lembaga · Kelompok Masyarakat · Instansi Vertikal
Bansos · Uang         → Perorangan SAJA
Bansos · Barang       → Lembaga · Perorangan · Kelompok Masyarakat
BK     · Uang         → Pemerintah Desa
```

Note bansos: money is the narrowest, goods the widest. So switching the form from goods to money has to narrow the recipient choices, and a value already picked can become invalid. The form clears it; if it were only hidden, an illegal combination could be saved through a UI that looks correct.

The rule is one table in `ApprovedProposal::VALID_RECIPIENTS`, not a chain of `if`s, so adding the next rule is editing data.

### 3. Status is computed, not chosen

```
realization 0                          → Disahkan
0 < realization < budget               → Sebagian Cair
realization >= approved budget         → Cair
```

Staff do not pick the status. They have already filled in the quarterly figures, and asking for the status afterward opens the door to the two contradicting each other, a row that reads "Cair" with Rp 0 realized.

`approved_budget > 0` must be checked. Imported rows do not always include it, and without that guard `$disbursed >= 0` is always true, stamping every row "Cair" the moment a single rupiah is disbursed.

`undisbursed_budget` is not used in the computation. It is filled in by hand and can go stale; the sum of `disbursements` rows reflects money that actually moved.

Cancellation is the only manual case, because a cancellation leaves no numeric trace. And `cancelled` is not "rejected": rejected means it was never approved, cancelled means it was approved and then withdrawn. Merging them makes the record contradict the SK that actually exists.

### 4. Duplicate detection excludes Bantuan Keuangan

BK flows to village governments, and the same village does receive it every year, that is how ADD works. Flagging it as a duplicate would accuse a legitimate disbursement of being an anomaly, and 1,124 BK rows would drown out the real hibah/bansos findings that need review.

The exclusion is written inside the detector (`scopeDuplicateCheckable`), not as a page filter, so the next caller does not miss it. And the Duplicate Detection tab is not rendered on the BK report: an empty tab reads as "checked, all clear" when it actually means "never checked".

Grouping is by name + address, not name alone. Rows without an address are skipped, not grouped together, because without an address there is no proof. After this rule: 1,769 false positives dropped to 8 real findings.

### 5. The importer matches exactly, it does not guess

The old `mapPeruntukan()` guessed from words ("contains *keuangan* → bk"), and its guess changed between imports: `BANTUAN KEUANGAN DARI ADD` was recorded as `hibah` for 2024 and `bk` for 2025, 562 rows in each year, and nobody noticed until the data was inspected two years later.

Now it is matched exactly against the list of valid values. Anything that does not match is rejected with a reason that names the cell, and the import does not stop. A 4,441-row file once failed midway and left a half-committed batch.

`TemplateExport` defines the shape of the file OPDs send back. Update the template before asking for data, not after.

### 6. A recipient is an entity, and its identity is name + address

Before v0.3.0 a recipient was just text on each proposal, so a madrasah receiving aid three years in a row was three separate texts that happened to match. `nawasara_hibah_recipients` makes it an entity; proposals point to it.

The linking happens in the model's `saving` hook, not in each caller, so it applies to the form, the importer, and the seeder alike. A master with gaps makes the Recipient page show an incomplete picture, and that is more misleading than an empty one.

Its identity is name + address, and both parts matter. Name alone would merge many different "MDT MIFTAHUL HUDA" into one receiving history, a wrong answer that looks plausible, far harder to find than a straight duplicate row. An empty address is never merged, for the same reason: without an address there is no proof.

`recipient_name` on the proposal is deliberately not deleted. It holds the name as written on that proposal's SK, while the master holds the recipient's identity. The master may have its spelling cleaned up; the proposal has to keep matching the document that approved it, because that is what is checked in an audit.

The Recipient page sits outside the `{purpose}` route group: its value is precisely that it shows all three purposes at once. The purpose badges reveal recipients drawing from more than one source, which is not visible from any single proposal list.

### 7. `pd` is in the enum, not in the constant

The `bk_type` column includes `'pd'`, but `BK_TYPES` does not yet. Its full name and whether it stays are not settled, and the 2024/2025 data only contains ADD. Offering an option staff do not understand ends with the option filled in at random. Adding it later is a one-line constant change; adding an enum value in MySQL rewrites the whole table.

## Actors

| Actor | Task | Scope |
|---|---|---|
| Operator OPD | Enter proposals, record realization, export | Own OPD |
| Admin-Hibah | Bulk import, monitor across OPDs | All OPDs |

OPD scope is not a permission. It is enforced by a global scope via `ScopedToOpd` from `nawasara/registry`. Privileged roles (`developer`, `hibah-admin`) see everything; anyone without a membership sees nothing (fail-closed).

## Menu

One workspace with sections inside it, not several separate workspaces, because an open workspace hides the others from the sidebar, and these are three things staff move back and forth between:

```
BANTUAN DAERAH
  [Hibah]              Hibah Uang · Hibah Barang · Laporan
  [Bansos]             Bansos Uang · Bansos Barang · Laporan
  [Bantuan Keuangan]   Umum · Khusus · Laporan
  [Penerima]           Daftar Penerima      ← across purposes
  [Pengaturan]         Impor Data
```

A section marker is a submenu entry without a `url`, using the `section` key (supported by `nawasara/ui` since v0.1.19).

This workspace does not declare a permission. `accessible()` filters by that one value alone, so gating it with `hibah.hibah.view` would hide the entire workspace from staff who only have rights to bansos. Gating lives on each section and each page.

## Permissions

```
hibah.hibah.view / create / update
hibah.bansos.view / create / update
hibah.bantuan-keuangan.view / create / update
hibah.approved-proposal.view / update
hibah.disbursement.update
hibah.report.export
hibah.recipient.view        # Recipient page (across purposes)
hibah.import                # admin
```

Gated per purpose, not per submenu: staff who may view hibah uang almost always may view hibah barang too. All three are currently granted to the same role; they are split so they can be broken apart later without touching the schema.

The `hibah.` prefix follows the package name, not the workspace label. That is what makes a permission traceable back to the package it came from.

## Setup

```bash
composer require nawasara/hibah
php artisan migrate
php artisan db:seed --class="Nawasara\Hibah\Database\Seeders\PermissionSeeder"
```

Tailwind v4, register it in `resources/css/app.css`:

```css
@source "../../vendor/nawasara/hibah";
```

Without that line, none of the Tailwind classes in this package's blades compile: the display loses its background and the layout breaks, and all that is left is the classes that happen to be used by other packages too.

Import data:

```bash
php artisan hibah:import "FORM BANTUAN DAERAH 2026.xlsx" 2026
```

For large Excel files (>20 MB), convert to CSV first and then use `ApprovedProposalImport::importCsv()`. PhpSpreadsheet loads the entire workbook into memory, and a 34 MB file once used 7.6 GB of RAM because of 524 thousand empty styled rows.

## Architecture

- One container and image shared with Nawasara (port 7100).
- `hibah.ponorogo.go.id` is an access alias (DNS CNAME + Cloudflare Tunnel), not a separate routing context.
- Components are split by concern: one component, one save button. The detail page was previously 199 lines of PHP + 414 of blade handling four concerns, and uploading monev evidence re-rendered the whole page.

## Roadmap

- BK recipients other than village governments, if it turns out there are any
- Change history for phased budgets (currently only before/after)
- A cross-purpose annual report for leadership

## License

MIT
