<?php

function getLabaRugi($tanggal_start, $lokasi=null) {
    
    $sql = new Cahkampung\Landadb(config('DB')['db']);

    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;

    $arr = [];

    $arr_klasifikasi = [
        "PEMASUKAN" => "'Pendapatan', 'Pendapatan Usaha', 'Pendapatan Non Usaha'",
        "HPP" => "'Hpp'",
        "PENGELUARAN" => "'Biaya Operasional', 'Biaya Non Operasional'"
    ];
//        print_r($arr_klasifikasi);die();
//        $index = 0;

    foreach ($arr_klasifikasi as $index => $akun) {

        $arr[$index]['nama'] = $index;
        $arr[$index]['total'] = 0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->customWhere("tipe IN($akun)")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();


        foreach ($getakun as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_start);
//                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($lokasi) && !empty($lokasi)) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $lokasi);
            }
            $gettransdetail = $sql->find();
            if (intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) {
                $arr[$index]['detail'][$key]['kode'] = $val->kode;
                $arr[$index]['detail'][$key]['nama'] = $val->nama;
                $arr[$index]['detail'][$key]['nominal'] = intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                $arr[$index]['total'] += $arr[$index]['detail'][$key]['nominal'];
            }
        }
    }
    
    return $arr;
}

