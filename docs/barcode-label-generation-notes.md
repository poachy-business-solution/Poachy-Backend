# Barcode Label Generation Notes

This is a deferred scope note for backend-generated barcode labels/images. The barcode work currently supports lookup values, offline sync, manual/imported/scanned/generated barcode records, and pending unknown-barcode suggestions. It does not generate printable label files yet.

## When to implement

Implement this only when stores need Poachy to print shelf labels, product stickers, package labels, or internal barcode labels. Barcode lookup values are enough for POS/mobile scanning until printing is required.

## Required pieces

- Barcode rendering library for converting stored barcode values into images, likely SVG first and PNG later if needed.
- Label templates defining what appears on the label: barcode image, product name, SKU, price, UOM/package name, store name, and later batch/expiry data if required.
- Template sizes for actual printers/paper, for example small sticker, shelf label, A4 sheet, or thermal label roll.
- Backend endpoint such as `GET /api/v1/tenant/product-barcodes/{barcode}/label?format=svg|png|pdf&template=small|shelf|thermal`.
- Permission checks, probably `manage-products`, because label generation exposes product/price metadata and supports operational printing.
- On-demand generation first; persistent storage can be added later if print volume or rendering cost requires caching.
- PDF support only after printer/page sizes are known.
- Full OpenAPI documentation and tests for content type, inactive/unknown barcode handling, product/variant/UOM labels, and permission gating.

## Suggested phases

1. SVG label endpoint generated on demand.
2. PNG output if mobile clients or printers need image files.
3. PDF/thermal label templates after printer requirements are confirmed.
