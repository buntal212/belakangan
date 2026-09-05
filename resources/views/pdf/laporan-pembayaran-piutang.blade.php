<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm 10mm 12mm 10mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8.5pt; color: #000; }
        .header { text-align: center; line-height: 1.45; margin-bottom: 12px; }
        .store-name { font-size: 12pt; font-weight: bold; }
        .report-title { font-size: 11pt; font-weight: bold; }
        .total-summary { width: 100%; border-bottom: 1px solid #555; padding-bottom: 7px; margin-bottom: 10px; }
        .total-summary td { vertical-align: bottom; }
        .summary-label { font-size: 8.5pt; }
        .summary-value { font-size: 10pt; font-weight: bold; }
        .transaction { width: 100%; margin-bottom: 8px; border: 1px solid #999; page-break-inside: avoid; }
        .transaction-summary { width: 100%; table-layout: fixed; border-collapse: collapse; background: #eee; }
        .transaction-summary td { padding: 5px 6px; vertical-align: top; }
        .label { font-size: 8pt; }
        .value { font-weight: bold; }
        .money { text-align: right; white-space: nowrap; }
        .payment-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .payment-table thead { display: table-header-group; }
        .payment-table th, .payment-table td { padding: 4px 6px; border-top: 1px solid #aaa; text-align: left; vertical-align: top; }
        .payment-table th { background: #f5f5f5; font-weight: bold; }
        .payment-table th.money, .payment-table td.money { text-align: right; }
        .footer { position: fixed; bottom: -7mm; left: 0; right: 0; text-align: center; font-size: 8pt; }
        .page-number:after { content: 'Halaman ' counter(page) ' dari ' counter(pages); }
    </style>
</head>
<body>
    @php
        $rupiah = fn ($nilai) => 'Rp ' . number_format((float) $nilai, 2, ',', '.');
        $tanggal = fn ($nilai) => $nilai ? \Carbon\Carbon::parse($nilai)->translatedFormat('d F Y') : '-';
    @endphp

    <div class="header">
        <div class="store-name">JANGUR KERAMIK</div>
        <div class="report-title">LAPORAN HISTORI PEMBAYARAN PIUTANG</div>
        <div>Periode {{ $jenisTanggal === 'hutang' ? 'Tanggal Hutang' : 'Tanggal Pembayaran' }} : {{ $tanggal($periodeAwal) }} - {{ $tanggal($periodeAkhir) }}</div>
        <div>Tanggal Cetak : {{ $tanggalCetak->translatedFormat('d F Y H:i') }}</div>
    </div>

    <table class="total-summary">
        <tr>
            <td>
                <div class="summary-label">Total Pembayaran</div>
                <div class="summary-value">{{ $rupiah($totalPembayaran) }}</div>
            </td>
            <td class="money">{{ $jumlahTransaksi }} transaksi ditemukan</td>
        </tr>
    </table>

    @foreach ($transaksi as $nota)
        <div class="transaction">
            <table class="transaction-summary">
                <tr>
                    <td style="width: 27%">
                        <div class="label">No. Penjualan</div><div class="value">{{ $nota['no_penjualan'] }}</div>
                        <div class="label" style="margin-top: 3px">Tgl Hutang</div><div>{{ $tanggal($nota['tgl_hutang']) }}</div>
                    </td>
                    <td style="width: 27%"><div class="label">Pelanggan</div><div class="value">{{ $nota['nama_pelanggan'] ?: 'Non pelanggan' }}</div></td>
                    <td class="money" style="width: 16%"><div class="label">Total Nota</div><div>{{ $rupiah($nota['total_nota']) }}</div></td>
                    <td class="money" style="width: 16%"><div class="label">Total Dibayar</div><div class="value">{{ $rupiah($nota['total_dibayar_nota']) }}</div></td>
                    <td class="money" style="width: 14%"><div class="label">Sisa Piutang</div><div class="value">{{ $rupiah($nota['sisa_piutang_nota']) }}</div></td>
                </tr>
            </table>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th style="width: 18%">Tanggal Bayar</th>
                        <th style="width: 28%">No. Pembayaran</th>
                        <th style="width: 17%">Cara Bayar</th>
                        <th style="width: 19%">Keterangan</th>
                        <th class="money" style="width: 18%">Nominal Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nota['rincian'] as $rincian)
                        <tr>
                            <td>{{ $tanggal($rincian['tgl_bayar']) }}</td>
                            <td>{{ $rincian['nopembayaran'] ?: '-' }}</td>
                            <td>{{ $rincian['cara_bayar'] ?: '-' }}</td>
                            <td>{{ $rincian['keterangan'] ?: '-' }}</td>
                            <td class="money value">{{ $rupiah($rincian['jumlah']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer"><span class="page-number"></span></div>
</body>
</html>
