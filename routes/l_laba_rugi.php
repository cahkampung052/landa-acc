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
         * panggil function saldo laba rugi, karena digunakan juga di laporan neraca
         */
        $labarugi = getLabaRugi($tanggal_start, $tanggal_end, $params['m_lokasi_id']);
        $arr = $labarugi['data'];
        $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
        $biaya = isset($labarugi['total']['BIAYA']) ? $labarugi['total']['BIAYA'] : 0;
        $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
        $data['total'] = $pendapatan - $biaya - $beban;
        
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