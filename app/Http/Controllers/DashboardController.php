<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Hash;
use App\Cash;
use App\User;
use App\Activity;
use File;
use Auth;
use DB;

class DashboardController extends Controller
{
    public function dashboard()
    {
    	$debit = '';
        $credit = '';
        $saldo = '';
        $credit_last_month = '';
        $check_debit         = Cash::where('c_jenis', 'D')->sum('c_jumlah');
        $check_credit        = Cash::where('c_jenis', 'D')->sum('c_jumlah');
        $month              = date('m');
        $year               = date('Y');
        $last_month         = $month-1%12;
        if($check_debit == NULL && $check_credit == NULL){
            $debit = 0;
            $credit = 0;
            $saldo = 0;
            $credit_last_month = 0;
        } elseif($check_debit != NULL && $check_credit == NULL){
            $debit = Cash::where('c_jenis', 'D')->sum('c_jumlah');
            $credit = 0;
            $saldo = $debit - $credit;
            $credit_last_month = Cash::where('c_jenis', 'K')->whereYear('c_tanggal', '=', ($last_month==0?($year-1):$year))->whereMonth('c_tanggal', '=', ($last_month==0?'12':$last_month))->sum('c_jumlah');
        } elseif($check_debit == NULL && $check_credit != NULL){
            $debit = 0;
            $credit = Cash::where('c_jenis', 'K')->sum('c_jumlah');
            $saldo = $debit - $credit;
            $credit_last_month = Cash::where('c_jenis', 'K')->whereYear('c_tanggal', '=', ($last_month==0?($year-1):$year))->whereMonth('c_tanggal', '=', ($last_month==0?'12':$last_month))->sum('c_jumlah');
        } else {
            $debit = Cash::where('c_jenis', 'D')->sum('c_jumlah');
            $credit = Cash::where('c_jenis', 'K')->sum('c_jumlah');
            $saldo = $debit - $credit;
            $credit_last_month = Cash::where('c_jenis', 'K')->whereYear('c_tanggal', '=', ($last_month==0?($year-1):$year))->whereMonth('c_tanggal', '=', ($last_month==0?'12':$last_month))->sum('c_jumlah');
        }
        return view('admin.dashboard.dashboard')->with(compact('saldo', 'debit', 'credit', 'credit_last_month'));
    }

    public function grafik()
    {
        $data_debit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_debit'),
            \DB::raw("DATE_FORMAT(c_tanggal, '%M %Y') as month"), 'c_jenis')
            ->where('c_jenis', 'D')
            ->whereYear('c_tanggal', date('Y', strtotime("-1 year")))
            ->groupBy(['month', 'c_jenis'])
            ->orderBy('month', 'desc')
            ->get();

        $data_credit = Cash::select(\DB::raw('SUM(c_jumlah) as jumlah_kredit'),
            \DB::raw("DATE_FORMAT(c_tanggal, '%M %Y') as month"), 'c_jenis')
            ->where('c_jenis', 'K')
            ->whereYear('c_tanggal', date('Y', strtotime("-1 year")))
            ->groupBy(['month', 'c_jenis'])
            ->orderBy('month', 'desc')
            ->get();

        foreach ($data_debit as $key => $debit) {
            $row[] = array('month' => $debit->month, 'debit' => $debit->jumlah_debit, 'kredit' => $data_credit[$key]->jumlah_kredit);
        }

        echo json_encode($row);
    }

    public function riwayat($parameter = null)
    {
        $result_debit   = array();
        $result_credit  = array();

        $tanggal        = date('Y-m-d', strtotime($parameter));
        $data_debit     = Cash::where('c_jenis', 'D')->where('c_tanggal', '=', $tanggal)->get();
        $data_credit    = Cash::where('c_jenis', 'K')->where('c_tanggal', '=', $tanggal)->get();

        foreach ($data_debit as $value) {
            $row = array(
                'tanggal' => date('d-m-Y', strtotime($value->c_tanggal)),
                'keterangan' => $value->c_transaksi,
                'jumlah' => $value->c_jumlah
            );

            $result_debit[] = $row;
        }

        foreach ($data_credit as $value) {
            $row = array(
                'tanggal' => date('d-m-Y', strtotime($value->c_tanggal)),
                'keperluan' => $value->c_transaksi,
                'jumlah' => $value->c_jumlah
            );

            $result_credit[] = $row;
        }

        $result_array = array('result_credit'=>$result_credit, 'result_debit'=>$result_debit);

        echo json_encode($result_array);
    }

    public function profil()
    {
        $tgllahir = User::select('tgl_lahir')
            ->where('id', Auth::user()->id)->first();
        $date = [];
        $date = explode('-', $tgllahir->tgl_lahir);
        $day = $date[2];
        $month = $date[1];
        $year = $date[0];
        return view('admin.profile.index')->with(compact('day', 'month', 'year'));
    }

    public function updateNama(Request $request)
    {
        DB::beginTransaction();
        try{
            User::where('id', Auth::user()->id)->update([
                'name' => $request->nama
            ]);
            DB::commit();
            return redirect('/profil')->with('flash_message_success', 'Nama Anda berhasil diubah!');
        }catch (\Exception $e){
            DB::rollback();
            return redirect('/profil')->with('flash_message_error', 'Nama Anda gagal diubah!');
        }
    }

    public function updateEmail(Request $request)
    {
        DB::beginTransaction();
        try{
            User::where('id', Auth::user()->id)->update([
                'email' => $request->email
            ]);
            DB::commit();
            return redirect('/profil')->with('flash_message_success', 'Email Anda berhasil diubah!');
        }catch (\Exception $e){
            DB::rollback();
            return redirect('/profil')->with('flash_message_error', 'Email Anda gagal diubah!');
        }
    }

    public function updateUsername(Request $request)
    {
        DB::beginTransaction();
        try{
            User::where('id', Auth::user()->id)->update([
                'username' => $request->username
            ]);
            DB::commit();
            return redirect('/profil')->with('flash_message_success', 'Username Anda berhasil diubah!');
        }catch (\Exception $e){
            DB::rollback();
            return redirect('/profil')->with('flash_message_error', 'Username Anda gagal diubah!');
        }
    }

    public function updatePassword(Request $request)
    {
        DB::beginTransaction();
        try{
            if ($request->oldPassword == "" || $request->newPassword == "" || $request->vernewPassword == ""){
                return redirect('/profil')->with('flash_message_error', 'Lengkapi data!');
            }

            $pwd = User::where('id', Auth::user()->id)->first();
            $check_pwd = Hash::check($request->oldPassword, $pwd->password, [true]);

            if ($check_pwd == false){
                return redirect('/profil')->with('flash_message_error', 'Kata sandi tidak ditemukan!');
            }else if ($request->vernewPassword != $request->newPassword){
                return redirect('/profil')->with('flash_message_error', 'Verifikasi kata sandi baru salah!');
            } else if ($check_pwd == true && $request->vernewPassword == $request->newPassword){
                User::where('id', Auth::user()->id)->update([
                    'password' => bcrypt($request->newPassword)
                ]);
                DB::commit();
                return redirect('/profil')->with('flash_message_success', 'Kata sandi Anda berhasil diubah!');
            }
        }catch (\Exception $e){
            DB::rollback();
            return redirect('/profil')->with('flash_message_error', 'Kata sandi Anda gagal diubah!');
        }
    }

    public function updateTtl(Request $request)
    {
        if ($request->tempat == "" || $request->tanggal == "" || $request->bulan == "" || $request->tahun == "") {
            return redirect('/profil')->with('flash_message_error', 'Lengkapi data!');
        } else {
            DB::beginTransaction();
            try{
                $tempat = $request->tempat;
                $tgllahir = $request->tahun . '-' . $request->bulan . '-' . $request->tanggal;
                User::where('id', Auth::user()->id)->update([
                    'tempat_lahir' => $tempat,
                    'tgl_lahir' => $tgllahir
                ]);
                DB::commit();
                return redirect('/profil')->with('flash_message_success', 'Tempat, tanggal lahir Anda berhasil diubah!');
            }catch (\Exception $e){
                DB::rollback();
                return redirect('/profil')->with('flash_message_error', 'Tempat, tanggal lahir Anda gagal diubah!');
            }
        }
    }

    public function updateAlamat(Request $request)
    {
        if ($request->alamat == "") {
            return redirect('/profil')->with('flash_message_error', 'Lengkapi data!');
        } else {
            DB::beginTransaction();
            try{
                User::where('id', Auth::user()->id)->update([
                    'address' => $request->alamat
                ]);
                DB::commit();
                return redirect('/profil')->with('flash_message_success', 'Alamat Anda berhasil diubah!');
            }catch (\Exception $e){
                DB::rollback();
                return redirect('/profil')->with('flash_message_error', 'Alamat Anda gagal diubah!');
            }
        }
    }

    public function updateFoto(Request $request)
    {
        DB::beginTransaction();
        try{

            if ($request->hasFile('foto')) {
                $image_tmp = Input::file('foto');
                $file = $request->file('foto');
                $image_size = $image_tmp->getSize(); //getClientSize()
                $maxsize = '2097152';
                if ($image_size < $maxsize) {

                    if ($image_tmp->isValid()) {

                        $namefile = $request->current_img;

                        if ($namefile != "") {

                            $path = 'public/images/' . $namefile;

                            if (File::exists($path)) {
                                # code...
                                File::delete($path);
                            }

                        }

                        $extension = $image_tmp->getClientOriginalExtension();
                        $filename = date('YmdHms') . rand(111, 99999) . '.' . $extension;
                        $image_path = 'public/images';

                        if (!is_dir($image_path )) {
                            mkdir("public/images", 0777, true);
                        }

                        ini_set('memory_limit', '256M');
                        $file->move($image_path, $filename);
                        User::where('id', Auth::user()->id)->update(['img' => $filename]);
                        DB::commit();
                        return redirect('/profil')->with('flash_message_success', 'Foto profil Anda berhasil diperbarui!');
                    }
                } else {

                    return redirect()->back()->with('flash_message_error', 'Foto profil gagal diperbarui...! Ukuran file terlalu besar');

                }
            }

        }catch (\Exception $e){
            DB::rollback();
            return redirect('/profil')->with('flash_message_error', 'Foto profil Anda gagal diperbarui! =>'. $e);
        }
    }

    public function history()
    {
        $date = DB::table('activity')
            ->select(DB::raw("DATE_FORMAT(date, '%d %M %Y') as date"))
            ->orderBy('date', 'desc')
            ->distinct()
            ->get();
        $data = DB::table('activity')
            ->select(DB::raw("DATE_FORMAT(date, '%d %M %Y') as date"),
                'users.name as user',
                'activity.action',
                'activity.title',
                'activity.note',
                'activity.oldnote',
                DB::raw("DATE_FORMAT(date, '%d %M %Y %H:%m:%s') as tgl"))
            ->join('users', 'activity.iduser', '=', 'users.id')
            ->orderBy('activity.date', 'desc')
            ->get();
        return view('admin.history.index')->with(compact('data', 'date'));
    }
}
