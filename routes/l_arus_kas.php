<?php

function validasi($data, $custom = array()) {
    $validasi = array(
//        'parent_id' => 'required',
//        'kode'      => 'required',
//        'nama'      => 'required',
            // 'tipe' => 'required',
    );
//    GUMP::set_field_name("parent_id", "Akun");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

$app->get('/acc/l_arus_kas/laporan', function ($request, $response) {
    $params = $request->getParams();

    $sql = $this->db;
    $validasi = validasi($params);
    if ($validasi === true) {

        //tanggal awal
        $tanggal_awal = new DateTime($params['startDate']);
        $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

        //tanggal akhir
        $tanggal_akhir = new DateTime($params['endDate']);
        $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggal_start = $tanggal_awal->format("Y-m-d");
        $tanggal_end = $tanggal_akhir->format("Y-m-d");



        $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
        $data['disiapkan'] = date("d-m-Y, H:i");
        $data['lokasi'] = $params['nama_lokasi'];

        if (isset($params['m_lokasi_id'])) {
            $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
            /*
             * jika lokasi punya child
             */
            if (!empty($lokasiId)) {
                $lokasiId[] = $params['m_lokasi_id'];
                $lokasiId = implode(",", $lokasiId);
            }
            /*
             * jika lokasi tidak punya child
             */ else {
                $lokasiId = $params['m_lokasi_id'];
            }
        }

        $data['total_saldo'] = 0;

        $arr = [];

        $arr["Aktivitas Operasi"] = [];
        $arr["Investasi"] = [];
        $arr["Pendanaan"] = [];

        
        /**
        * Ambil laba / rugi
        */
        $totalLabaRugi = getLabaRugi("1970-01-01", $tanggal_end, null, false);

       /**
        * Ambil akun laba rugi
        */
        $labarugi = $sql->find("select * from acc_m_akun_peta where type = 'Laba Rugi Berjalan'");
        $akunLabaRugi = isset($labarugi->m_akun_id) ? $labarugi->m_akun_id : 0;


        /*
         * ambil arus yg tipe arus
         */
        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->customWhere("tipe_arus IN('Aktivitas Operasi', 'Investasi', 'Pendanaan')")
                ->where("is_tipe", "=", 1)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();


//        print_r($getakun);
//        die();
        $index = 0;
        foreach ($getakun as $key => $val) {
            $arr[$val->tipe_arus][$index] = (array) $val;
            /*
             * ambil child akun
             */
            $akunId = getChildId("acc_m_akun", $val->id);
//                print_r($akunId);
            foreach ($akunId as $det => $detail) {
//                $arr[$val->tipe_arus][$index]['detail'][$det] = (array) $detail;
                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail")
                        ->where('m_akun_id', '=', $detail)
                        ->andWhere('date(tanggal)', '<', $tanggal_start);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $getsaldoawal = $sql->find();
                $saldo_awal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
                
                if ($detail == $akunLabaRugi) {
                    $saldo_awal += $totalLabaRugi;
                }
//
                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit, acc_m_akun.kode, acc_m_akun.nama, acc_m_akun.id as idAkun, acc_m_akun.tipe")
                        ->from("acc_trans_detail")
                        ->join("JOIN", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                        ->where('acc_trans_detail.m_akun_id', '=', $detail)
                        ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
                if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                    $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $params['m_lokasi_id']['id']);
                }
                $gettransdetail = $sql->find();

                $saldo_periode = intval($gettransdetail->debit) - intval($gettransdetail->kredit);

                $arr[$val->tipe_arus][$index]['detail'][$det]['id'] = $gettransdetail->idAkun;
                $arr[$val->tipe_arus][$index]['detail'][$det]['nama'] = $gettransdetail->kode . " - " . $gettransdetail->nama;
                if($gettransdetail->tipe == "HARTA"){
                    $arr[$val->tipe_arus][$index]['detail'][$det]['saldo'] = ($saldo_periode - $saldo_awal) * -1;
                    $data['total_saldo'] += ($saldo_periode - $saldo_awal) * -1;   
                }else{
                    $arr[$val->tipe_arus][$index]['detail'][$det]['saldo'] = ($saldo_periode - $saldo_awal);
                    $data['total_saldo'] += ($saldo_periode - $saldo_awal); 
                }
                
//                $index2++;
            }
            $index++;
        }

        /*
         * get akun kas
         */
        $kas = $sql->select("*")->from("acc_m_akun")->where("is_tipe", "=", 0)->where("is_kas", "=", 1)->findAll();

        $data['saldo_awal'] = 0;
        $data['saldo_akhir'] = 0;
        foreach ($kas as $key => $val) {
            
            /*
             * saldo awal
             */
            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(tanggal)', '<', $tanggal_start);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $getsaldoawal = $sql->find();
            $data['saldo_awal'] += intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);

            /*
             * saldo akhir
             */
            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($params['m_lokasi_id']['id']) && !empty($params['m_lokasi_id']['id'])) {
                $sql->andWhere('m_lokasi_id', '=', $params['m_lokasi_id']['id']);
            }
            $getsaldoawal = $sql->find();
            $data['saldo_akhir'] += intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
        }
//        print_r($arr);
//        die();
//        $data['kas'] = $data['total_saldo'] - $data['saldo_awal'];

        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/arusKas.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/arusKas.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            echo $content;
            echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
        } else {
            return successResponse($response, ["data" => $data, "detail" => $arr]);
        }
    } else {
        return unprocessResponse($response, $validasi);
    }
});
