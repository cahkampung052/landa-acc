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

$app->get('/acc/l_laba_rugi/laporan', function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);
//    die();
    $sql = $this->db;
    $validasi = validasi($params);
    if ($validasi === true) {

        /*
         * tanggal awal
         */
        $tanggal_awal = new DateTime($params['startDate']);
        $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

        /*
         * tanggal akhir
         */
        $tanggal_akhir = new DateTime($params['endDate']);
        $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $tanggal_start = $tanggal_awal->format("Y-m-d");
        $tanggal_end = $tanggal_akhir->format("Y-m-d");

        /*
         * return untuk header
         */
        $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
        $data['disiapkan'] = date("d-m-Y, H:i");
        $data['lokasi'] = "Semua";
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $data['lokasi'] = $params['m_lokasi_nama'];
        }
        
        /*
         * ambil child lokasi
         */
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        if(!empty($lokasiId)){
            array_push($lokasiId, $params['m_lokasi_id']);
            $lokasiId = implode(",", $lokasiId);
        }else{
            $lokasiId = $params['m_lokasi_id'];
        }
        


        $data['saldo_awal'] = 0;
        $data['total_saldo'] = 0;

        /*
         * get akun parent 0, akun utama
         */
        $klasifikasi = $sql->select("*")
                ->from("acc_m_akun")
                ->where("parent_id", "=", 0)
                ->findAll();
        $arr = [];

        /*
         * proses perulangan
         */
        foreach ($klasifikasi as $index => $akun) {
            $arr[$index] = (array) $akun;
            $arr[$index]['total'] = 0;
            /*
             * ambil child akun
             */
            $akunId = getChildId("acc_m_akun", $akun->id);
            

            $getakun = $sql->select("*")
                    ->from("acc_m_akun")
                    ->customWhere("id IN(". implode(',', $akunId).")")
                    ->orderBy("kode")
                    ->findAll();


            foreach ($getakun as $key => $val) {

                $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                        ->from("acc_trans_detail");
                        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                            $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
                        }
                        $sql->where('acc_trans_detail.m_akun_id', '=', $val->id)
                        ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                        ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
                
                $gettransdetail = $sql->find();
                if ((intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) || (intval($gettransdetail->debit) - intval($gettransdetail->kredit) < 0) || $val->is_tipe == 1) {
                    if ($val->is_tipe == 1) {
                        $arr[$index]['detail'][$val->id]['kode'] = $val->kode;
                        $arr[$index]['detail'][$val->id]['nama'] = $val->nama;
                        $arr[$index]['detail'][$val->id]['nominal'] = 0; 
                    } else {
//                        $arr[$index][$val->parent_id]['detail'][] = (array) $val;
                        $arr[$index]['detail'][$val->parent_id]['detail'][$key]['kode'] = $val->kode;
                        $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nama'] = $val->nama;
                        $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'] = intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                        $arr[$index]['total'] += $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'];
                        $arr[$index]['detail'][$val->parent_id]['nominal'] += $arr[$index]['detail'][$val->parent_id]['detail'][$key]['nominal'];
                    }
                    
                }
            }
        }
        
        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/labaRugi.html', [
                "data" => $data,
                "detail" => $arr,
                "totalsemua" => $arr[3]['total']-$arr[4]['total']-$arr[5]['total']-$arr[6]['total']+$arr[7]['total']-$arr[8]['total'],
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/labaRugi.html', [
                "data" => $data,
                "detail" => $arr,
                "totalsemua" => $arr[3]['total']-$arr[4]['total']-$arr[5]['total']-$arr[6]['total']+$arr[7]['total']-$arr[8]['total'],
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            echo $content;
            echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
        }else{
            return successResponse($response, ["data" => $data, "detail" => $arr]);
        }
        
        
    } else {
        return unprocessResponse($response, $validasi);
    }
});