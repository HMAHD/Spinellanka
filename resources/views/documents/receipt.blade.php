<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <meta name="referrer" content="no-referrer">
  <title>{{ isset($isInvoice) && $isInvoice ? 'Official Invoice' : 'Payment Receipt' }} — {{ $documentNumber }} — Spinellanka</title>
  <style>
    @page {
      size: A4;
      margin: 20mm;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #1e293b;
      line-height: 1.5;
      margin: 0;
      padding: 24px;
      background: #f8fafc;
    }
    .document-card {
      max-width: 800px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 40px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #0f172a;
      padding-bottom: 24px;
      margin-bottom: 24px;
    }
    .company-info h1 {
      font-size: 24px;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.5px;
      margin: 0 0 4px;
    }
    .company-info p {
      font-size: 13px;
      color: #64748b;
      margin: 0;
    }
    .doc-meta {
      text-align: right;
    }
    .doc-meta h2 {
      font-size: 20px;
      font-weight: 700;
      color: #0284c7;
      text-transform: uppercase;
      margin: 0 0 6px;
    }
    .doc-meta .doc-number {
      font-size: 14px;
      font-weight: 700;
      color: #0f172a;
      font-family: monospace;
    }
    .doc-meta .doc-date {
      font-size: 13px;
      color: #64748b;
      margin-top: 4px;
    }
    .section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 32px;
    }
    .info-block h3 {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #94a3b8;
      margin: 0 0 8px;
    }
    .info-block p {
      margin: 0 0 4px;
      font-size: 14px;
    }
    .info-block strong {
      color: #0f172a;
    }
    table.line-items {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }
    table.line-items th {
      background: #f1f5f9;
      color: #334155;
      font-size: 12px;
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
      text-align: left;
      padding: 10px 12px;
      border-bottom: 1px solid #cbd5e1;
    }
    table.line-items td {
      padding: 12px;
      font-size: 14px;
      border-bottom: 1px solid #e2e8f0;
    }
    table.line-items td.amount {
      text-align: right;
      font-variant-numeric: tabular-nums;
    }
    .totals-area {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 32px;
    }
    .totals-table {
      width: 320px;
      border-collapse: collapse;
    }
    .totals-table td {
      padding: 6px 12px;
      font-size: 14px;
    }
    .totals-table tr.grand-total td {
      border-top: 2px solid #0f172a;
      border-bottom: 2px solid #0f172a;
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      padding: 10px 12px;
    }
    .payment-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }
    .footer-section {
      border-top: 1px solid #e2e8f0;
      padding-top: 20px;
      font-size: 12px;
      color: #64748b;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    .checksum-box {
      font-family: monospace;
      font-size: 11px;
      color: #94a3b8;
      background: #f8fafc;
      padding: 6px 10px;
      border-radius: 4px;
      border: 1px solid #e2e8f0;
      word-break: break-all;
    }
    @media print {
      body {
        background: #ffffff;
        padding: 0;
      }
      .document-card {
        border: none;
        box-shadow: none;
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
    }
  </style>
</head>
<body>
  <div class="no-print" style="max-width: 800px; margin: 0 auto 16px; text-align: right;">
    <button onclick="window.print()" style="background: #0284c7; color: #ffffff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer;">
      Print / Save PDF
    </button>
  </div>

  <div class="document-card">
    <div class="header-row">
      <div class="company-info">
        <h1>SPINELLANKA</h1>
        <p>Premium Island Mobility &amp; Chauffeured Tours</p>
        <p>Negombo Road, Katunayake / Colombo, Sri Lanka</p>
        <p>Support WhatsApp: +94 77 123 4567 &bull; billing@spinellanka.com</p>
      </div>
      <div class="doc-meta">
        <h2>{{ isset($isInvoice) && $isInvoice ? 'Official Invoice' : 'Payment Receipt' }}</h2>
        <div class="doc-number">{{ $documentNumber }}</div>
        <div class="doc-date">Issued: {{ $issuedAt->format('d M Y, H:i') }} (Asia/Colombo)</div>
        <div style="margin-top: 6px;">
          <span class="payment-badge">Settled &bull; Paid</span>
        </div>
      </div>
    </div>

    <div class="section-grid">
      <div class="info-block">
        <h3>Billed To (Traveler)</h3>
        <p><strong>{{ $booking->leadTraveler?->full_name ?: ($booking->booker?->full_name ?: 'Valued Guest') }}</strong></p>
        <p>{{ $booking->leadTraveler?->email ?: $booking->booker?->email }}</p>
        <p>{{ $booking->leadTraveler?->phone ?: $booking->booker?->phone }}</p>
      </div>

      <div class="info-block">
        <h3>Reservation Details</h3>
        <p>Booking Reference: <strong>{{ $booking->reference }}</strong></p>
        <p>Service: <strong>{{ $booking->service_type->label() }}</strong></p>
        @if($booking->currentProposal && $booking->currentProposal->vehicle)
          <p>Allocated Vehicle: <strong>{{ $booking->currentProposal->vehicle->make }} {{ $booking->currentProposal->vehicle->model }} ({{ $booking->currentProposal->vehicle->license_plate }})</strong></p>
        @endif
        <p>Payment Method: <strong>{{ $transaction ? $transaction->payment_method->label() : 'Official Gateway' }}</strong></p>
        @if($transaction && $transaction->gateway_transaction_id)
          <p>Gateway Ref: <strong style="font-family: monospace;">{{ $transaction->gateway_transaction_id }}</strong></p>
        @endif
      </div>
    </div>

    <table class="line-items">
      <thead>
        <tr>
          <th>Description</th>
          <th style="text-align: right;">Quantity / Days</th>
          <th style="text-align: right;">Unit Price</th>
          <th style="text-align: right;">Amount</th>
        </tr>
      </thead>
      <tbody>
        @if($proposal && !empty($proposal->items))
          @foreach($proposal->items as $item)
            <tr>
              <td>
                <strong>{{ $item->description }}</strong>
              </td>
              <td style="text-align: right;">{{ $item->quantity }}</td>
              <td style="text-align: right;">{{ $proposal->currency }} {{ number_format($item->unit_price_minor_units / 100, 2) }}</td>
              <td class="amount">{{ $proposal->currency }} {{ number_format($item->total_amount_minor_units / 100, 2) }}</td>
            </tr>
          @endforeach
        @else
          <tr>
            <td>
              <strong>{{ $booking->service_type->label() }} Reservation (#{{ $booking->reference }})</strong>
            </td>
            <td style="text-align: right;">1</td>
            <td style="text-align: right;">{{ $transaction ? $transaction->currency : 'USD' }} {{ number_format(($transaction ? $transaction->amount_minor_units : 0) / 100, 2) }}</td>
            <td class="amount">{{ $transaction ? $transaction->currency : 'USD' }} {{ number_format(($transaction ? $transaction->amount_minor_units : 0) / 100, 2) }}</td>
          </tr>
        @endif
      </tbody>
    </table>

    <div class="totals-area">
      <table class="totals-table">
        <tr>
          <td>Subtotal:</td>
          <td style="text-align: right;">{{ $transaction ? $transaction->currency : 'USD' }} {{ number_format(($transaction ? $transaction->amount_minor_units : 0) / 100, 2) }}</td>
        </tr>
        <tr>
          <td>Taxes &amp; Expressway Tolls:</td>
          <td style="text-align: right;">Included</td>
        </tr>
        <tr class="grand-total">
          <td>Amount Settled:</td>
          <td style="text-align: right;">{{ $transaction ? $transaction->currency : 'USD' }} {{ number_format(($transaction ? $transaction->amount_minor_units : 0) / 100, 2) }}</td>
        </tr>
      </table>
    </div>

    <div class="footer-section">
      <div>
        <p style="margin: 0 0 2px;">Thank you for travelling with Spinellanka.</p>
        <p style="margin: 0;">This digital financial document is tamper-evident and generated automatically upon payment settlement.</p>
      </div>
      <div style="text-align: right;">
        <span style="font-size: 11px; color: #94a3b8; display: block; margin-bottom: 2px;">Verification Checksum (SHA-256):</span>
        <div class="checksum-box">{{ $checksum }}</div>
      </div>
    </div>
  </div>
</body>
</html>

