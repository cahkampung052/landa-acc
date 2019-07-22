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

$app->get('/acc/l_jurnal_umum/laporan', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
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



        $arr = [];
        $data['total_debit'] = 0;
        $data['total_kredit'] = 0;

        $index = 0;

        /*
         * ambil trans detail dari akun
         */
        $sql->select("acc_trans_detail.id, acc_trans_detail.kode, 
                acc_trans_detail.debit, 
                acc_trans_detail.kredit, 
                acc_trans_detail.keterangan, 
                acc_trans_detail.tanggal, 
                acc_m_akun.kode as kodeAkun, 
                acc_m_akun.nama as namaAkun,
                acc_m_lokasi.kode as kodeLokasi,
                acc_m_lokasi.nama as namaLokasi")
                ->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
        }
        $sql->join("join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                ->join("left join", "acc_m_lokasi", "acc_m_lokasi.id = acc_trans_detail.m_lokasi_id")
//                ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end)
                ->orderBy("acc_trans_detail.tanggal ASC,acc_trans_detail.id ASC");

        $gettransdetail = $sql->findAll();
        foreach ($gettransdetail as $keys => $vals) {
//                $arr[$index] = (array) $vals;
            if ($vals->debit == NULL) {
                $vals->debit = 0;
            }
            if ($vals->kredit == NULL) {
                $vals->kredit = 0;
            }


            $arr[$index]['tanggal2'] = $vals->tanggal;
            $vals->tanggal = date("d-m-Y", strtotime($vals->tanggal));
            $arr[$index] += (array) $vals;
            $index++;
            $data['total_debit'] += $vals->debit;
            $data['total_kredit'] += $vals->kredit;
        }


        /*
         * sorting array berdasarkan tanggal
         */

//        function cmp($a, $b) {
//            return strcmp($a['tanggal2'], $b['tanggal2']);
//        }
//
//        usort($arr, "cmp");

//        print_r($arr);die();

        $kode = "";
        $namaLokasi = "";
        foreach ($arr as $key => $val) {
            if ($val['kode'] == $kode && $val['kode'] != "" && $val['namaLokasi'] == $namaLokasi && $val['namaLokasi'] != "") {
                $arr[$key]['kode'] = "";
                $arr[$key]['kodeLokasi'] = "";
                $arr[$key]['namaLokasi'] = "";
                $arr[$key]['tanggal'] = "";
            } else {
                $kode = $val['kode'];
                $namaLokasi = $val['namaLokasi'];
            }
        }


        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/jurnalUmum.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/jurnalUmum.html', [
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
