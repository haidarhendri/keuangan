<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Cash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use DB;
use Response;
use Excel;
use PDF;
use App\Exports\CashflowExcel;
use App\Exports\CashflowPDF;

class LaporanController extends Controller
{
    public function chart()
    {
        return view('admin.laporan.chart');
    }

    public function chartBulanDebit($bulan = null)
    {
        $row = array();
        $month = explode(" ", $bulan);
        $date = date('m', strtotime($month[0]));
        $t = $month[1].'-'.$date.'-'.'1';
        $from = date('Y-m-d', strtotime($t));
        $to = date("Y-m-t", strtotime($from));

        $data_debit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_debit'),
            'c_tanggal as month', 'c_jenis')
            ->where('c_jenis', 'D')
            ->whereBetween('c_tanggal', [$from, $to])
            ->groupBy(['c_tanggal', 'c_jenis'])
            ->orderBy('c_tanggal', 'desc')
            ->get();

        foreach ($data_debit as $key => $debit) {
            $row[] = array('date' => $debit->month, 'debit' => $debit->jumlah_debit);
        }

        echo json_encode($row);
    }

    public function chartBulanKredit($bulan = null)
    {
        $row = array();
        $month = explode(" ", $bulan);
        $date = date('m', strtotime($month[0]));
        $t = $month[1].'-'.$date.'-'.'1';
        $from = date('Y-m-d', strtotime($t));
        $to = date("Y-m-t", strtotime($from));

        $data_credit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_kredit'),
            'c_tanggal as month', 'c_jenis')
            ->where('c_jenis', 'K')
            ->whereBetween('c_tanggal', [$from, $to])
            ->groupBy(['c_tanggal', 'c_jenis'])
            ->orderBy('c_tanggal', 'asc')
            ->get();

        foreach ($data_credit as $key => $credit) {
            $row[] = array('date' => $credit->month, 'kredit' => $credit->jumlah_kredit);
        }

        echo json_encode($row);
    }

    public function chartTahun($tahun = null)
    {
        $row = array();
        $data_debit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_debit'),
            \DB::raw("DATE_FORMAT(c_tanggal, '%M %Y') as month"), 'c_jenis')
            ->where('c_jenis', 'D')
            ->whereYear('c_tanggal', $tahun)
            ->groupBy(['month', 'c_jenis'])
            ->orderBy('month', 'desc')
            ->get();

        $data_credit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_kredit'),
            \DB::raw("DATE_FORMAT(c_tanggal, '%M %Y') as month"), 'c_jenis')
            ->where('c_jenis', 'K')
            ->whereYear('c_tanggal', $tahun)
            ->groupBy(['month', 'c_jenis'])
            ->orderBy('month', 'desc')
            ->get();

        foreach ($data_debit as $key => $debit) {
            $row[] = array('month' => $debit->month, 'debit' => $debit->jumlah_debit, 'kredit' => $data_credit[$key]->jumlah_kredit);
        }

        echo json_encode($row);
    }

    public function cashflow()
    {
        return view('admin.laporan.cashflow');
    }

    public function cashflowBulan($bulan = null)
    {
        $month = explode(" ", $bulan);

        $data = Cash::whereMonth('c_tanggal', date('m', strtotime($month[0])))
            ->whereYear('c_tanggal', $month[1])
            ->select(DB::raw("DATE_FORMAT(c_tanggal, '%d-%m-%Y') as c_tanggal"), 'c_transaksi', 'c_jumlah', 'c_jenis')
            ->get();

        return json_encode($data);
    }

    public function cashflowTahun($tahun = null)
    {

        $data = Cash::whereYear('c_tanggal', $tahun)
            ->select(DB::raw("DATE_FORMAT(c_tanggal, '%d-%m-%Y') as c_tanggal"), 'c_transaksi', 'c_jumlah', 'c_jenis')
            ->get();

        return json_encode($data);
    }

    public function excel($month, $year)
    {
        return (new CashflowExcel($month, $year))->download('Cashflow.xlsx');
    }

    public function pdf($month, $year)
    {
//        return (new CashflowPDF($month, $year))->download('Cashflow.pdf');
        if ($year == "null") {
            $bulan = explode(" ", $month);

            $cash =  Cash::query()
                ->whereMonth('c_tanggal', date('m', strtotime($bulan[0])))
                ->whereYear('c_tanggal', $bulan[1])
                ->select(DB::raw("DATE_FORMAT(c_tanggal, '%d-%m-%Y') as c_tanggal"),
                    'c_transaksi', 'c_jumlah', 'c_jenis')
                ->get();
            $periode = $month;
            $param = 'bulan';
        } else if ($month == "null") {

            $cash =  Cash::query()
                ->whereYear('c_tanggal', $year)
                ->select(DB::raw("DATE_FORMAT(c_tanggal, '%d-%m-%Y') as c_tanggal"),
                    'c_transaksi', 'c_jumlah', 'c_jenis')
                ->get();
            $periode = $year;
            $param = 'tahun';
        }
        $data['data'] = $cash;
        $data['periode'] = $periode;
        $data['param'] = $param;
        $pdf = PDF::loadView('admin.laporan.pdf', $data);
        return $pdf->download('Cashflow.pdf');
    }
}
