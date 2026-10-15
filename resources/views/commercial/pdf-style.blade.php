{{-- Shared look for every generated PDF (dompdf: tables for layout, DejaVu Sans for full French support). --}}
<style>
    @page { margin: 34px 40px 62px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; line-height: 1.5; color: #1f2a37; }
    h1, h2, h3, p { margin: 0; }
    .muted { color: #64748b; }
    .lines { white-space: pre-wrap; }
    table { width: 100%; border-collapse: collapse; }

    /* Header: dark brand band on the left, document identity on the right. */
    .head td { vertical-align: top; }
    .brand-cell { width: 220px; }
    .brand-band { background: #101b2b; padding: 12px 16px; border-radius: 4px; }
    .brand-band img { width: 170px; height: 57px; }
    .doc-id { text-align: right; padding-left: 20px; }
    .doc-kind { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #2563eb; font-weight: bold; }
    .doc-title { font-size: 21px; font-weight: bold; color: #101b2b; margin-top: 4px; }
    .doc-meta { margin-top: 8px; color: #475569; }
    .doc-meta strong { color: #1f2a37; }
    .rule { height: 3px; background: #2563eb; margin: 18px 0 20px; }

    /* Parties. */
    .parties td { width: 50%; vertical-align: top; padding: 0; }
    .party { border: 1px solid #dbe2ea; border-radius: 6px; padding: 12px 14px; }
    .party-gap { width: 14px !important; }
    .label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-bottom: 5px; }
    .party-name { font-size: 11.5px; font-weight: bold; color: #101b2b; margin-bottom: 3px; }

    /* Items. */
    h2.section { font-size: 11px; color: #101b2b; margin: 22px 0 8px; padding-bottom: 5px; border-bottom: 1px solid #dbe2ea; }
    .items th { background: #101b2b; color: #fff; font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; padding: 7px 8px; text-align: left; }
    .items td { padding: 8px; border-bottom: 1px solid #e5eaf0; vertical-align: top; }
    .items tr:nth-child(even) td { background: #f7f9fb; }
    .items .num, .items th.num { text-align: right; white-space: nowrap; }
    .item-name { font-weight: bold; color: #101b2b; }
    .description { font-size: 8.5px; color: #64748b; margin-top: 2px; }
    tr { page-break-inside: avoid; }

    /* Totals box, right aligned. */
    .totals-wrap td.spacer { width: 52%; }
    .totals td { padding: 5px 10px; }
    .totals .amount { text-align: right; white-space: nowrap; }
    .totals .grand td { background: #101b2b; color: #fff; font-size: 12px; font-weight: bold; padding: 9px 10px; }

    /* Info boxes. */
    .box { border: 1px solid #dbe2ea; border-left: 3px solid #2563eb; border-radius: 4px; padding: 10px 12px; margin-top: 12px; page-break-inside: avoid; }
    .box-title { font-weight: bold; color: #101b2b; margin-bottom: 4px; }
    .draft { margin-bottom: 14px; padding: 9px 12px; border: 2px solid #c2410c; background: #fff7ed; color: #9a3412; font-weight: bold; text-align: center; letter-spacing: 1px; }

    /* Signature blocks. */
    .signatures { margin-top: 26px; }
    .signatures table { page-break-inside: avoid; }
    .signatures td.sign { width: 48%; vertical-align: top; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 14px; }
    .signatures td.sign-gap { width: 4%; }
    .sign-role { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #2563eb; font-weight: bold; }
    .sign-name { font-size: 11px; font-weight: bold; color: #101b2b; margin-top: 4px; }
    .sign-field { margin-top: 10px; color: #475569; }
    .sign-field span { display: inline-block; width: 150px; border-bottom: 1px dotted #94a3b8; }
    .sign-area { height: 70px; margin-top: 8px; border: 1px dashed #94a3b8; border-radius: 4px; background: #fafbfc; }
    .sign-hint { margin-top: 4px; font-size: 8px; color: #94a3b8; text-align: center; }

    /* Legal text (CGV, contracts). */
    .article { margin-top: 14px; page-break-inside: avoid; }
    .article h3 { font-size: 10.5px; color: #101b2b; margin-bottom: 4px; }
    .article h3 span { color: #2563eb; }
    .article p { text-align: left; }
    .article .sub { font-weight: bold; color: #101b2b; margin-top: 8px; }
    .defs { margin: 4px 0 2px; }
    .defs td { padding: 3px 0; border-bottom: 1px solid #eef2f6; vertical-align: top; }
    .defs td.def-label { width: 38%; color: #64748b; padding-right: 12px; }

    /* Footer repeated on every page. */
    .footer { position: fixed; left: 0; right: 0; bottom: -40px; height: 30px; border-top: 1px solid #dbe2ea; padding-top: 6px; font-size: 7.5px; color: #64748b; }
    .footer td { vertical-align: top; }
    .pagenum:before { content: counter(page); }
</style>
<style>
    /* Local test mode stamp: a test document must never pass for a real one. */
    .test-stamp { margin-bottom: 14px; padding: 8px 12px; border: 2px dashed #b91c1c; background: #fef2f2; color: #991b1b; font-weight: bold; text-align: center; letter-spacing: 1px; }
    .test-watermark { position: fixed; top: 38%; left: 0; right: 0; text-align: center; font-size: 64px; font-weight: bold; color: #b91c1c; opacity: 0.08; transform: rotate(-30deg); }
</style>
@if(\App\Support\TestMode::enabled())
    <div class="test-watermark">MODE TEST</div>
@endif
