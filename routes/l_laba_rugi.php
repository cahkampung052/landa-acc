<?php

$app->get('/acc/l_laba_rugi/laporan', function ($request, $response) {

    $data['img'] = imgLaporan();

    $params = $request->getParams();
    $sql = $this->db;
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
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = "Semua";
    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $data['lokasi'] = $params['lokasi_nama'];
    }
    /*
     * panggil function saldo laba rugi, karena digunakan juga di laporan neraca
     */
    $labarugi = getLabaRugi($tanggal_start, $tanggal_end, $params['m_lokasi_id']);
    $arr = $labarugi['data'];
    $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
    $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
    $pendapatanLuarUsaha = isset($labarugi['total']['PENDAPATAN DILUAR USAHA']) ? $labarugi['total']['PENDAPATAN DILUAR USAHA'] : 0;
    $bebanLuarUsaha = isset($labarugi['total']['BEBAN DILUAR USAHA']) ? $labarugi['total']['BEBAN DILUAR USAHA'] : 0;
    $data['total'] = $pendapatan + $pendapatanLuarUsaha - $beban - $bebanLuarUsaha;
    $data['lr_usaha'] = $pendapatan - $beban;
    $data['is_detail'] = $params['is_detail'];

    foreach ($arr as $key => $value) {
        foreach ($value['detail'] as $keys => $values) {
            if ($values['nominal'] == 0) {
                unset($arr[$key]['detail'][$keys]);
            }
        }
    }

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/labaRugi.html', [
            "data" => $data,
            "detail" => $arr,
            "totalsemua" => $data['total'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-laba-rugi.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/labaRugi.html', [
            "data" => $data,
            "detail" => $arr,
            "totalsemua" => $data['total'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
//        pd($arr);
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
})->setName("labaRugi");

$app->get('/acc/l_laba_rugi/laporan_periode', function ($request, $response) {

    $data['img'] = imgLaporan();

    $params = $request->getParams();

//    print_die($params);

    $sql = $this->db;
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
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = "Semua";
    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $data['lokasi'] = $params['lokasi_nama'];
    }

    /*
     * generate tanggal per bulan
     */
    $tanggal_sekarang = $tanggal_start;
    $tanggal_akhir_bulan = date('Y-m-t', strtotime($tanggal_sekarang));
    $arr_tanggal = [];
    $add = true;
    while ($tanggal_akhir_bulan <= $tanggal_end) {
        $arr_tanggal[] = [
            'awal' => $tanggal_sekarang,
            'akhir' => $tanggal_akhir_bulan,
            'nama' => date('F', strtotime($tanggal_sekarang)),
            'number' => date('n', strtotime($tanggal_sekarang)),
        ];
        $tanggal_sekarang = date('Y-m-d', strtotime($tanggal_akhir_bulan . '+1 days'));
        $tanggal_akhir_bulan = date('Y-m-t', strtotime($tanggal_sekarang));
        if ($tanggal_sekarang > $tanggal_end) {
            $add = false;
        }

        if ($tanggal_akhir_bulan == $tanggal_end) {
            $add = false;
        }
    }

    /*
     * jika tanggal akhir bukan 30/31
     */
    if ($add) {
        $arr_tanggal[] = [
            'awal' => $tanggal_sekarang,
            'akhir' => $tanggal_end,
            'nama' => date('F', strtotime($tanggal_sekarang)),
            'number' => date('n', strtotime($tanggal_sekarang)),
        ];
    }

//    print_die($arr_tanggal);

    $arr_hasil = [];
    foreach ($arr_tanggal as $k => $v) {
        /*
         * panggil function saldo laba rugi, karena digunakan juga di laporan neraca
         */
        $labarugi = getLabaRugi($v['awal'], $v['akhir'], $params['m_lokasi_id']);

//        print_die($labarugi);

        $arr = $labarugi['data'];

        $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
        $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
        $pendapatanLuarUsaha = isset($labarugi['total']['PENDAPATAN DILUAR USAHA']) ? $labarugi['total']['PENDAPATAN DILUAR USAHA'] : 0;
        $bebanLuarUsaha = isset($labarugi['total']['BEBAN DILUAR USAHA']) ? $labarugi['total']['BEBAN DILUAR USAHA'] : 0;
        $data['total'][$v['number']] = $pendapatan + $pendapatanLuarUsaha - $beban - $bebanLuarUsaha;
        $data['lr_usaha'][$v['number']] = $pendapatan - $beban;

        foreach ($arr as $key => $value) {
            foreach ($value['detail'] as $keys => $values) {
                if ($values['nominal'] == 0) {
                    unset($arr[$key]['detail'][$keys]);
                } else {
                    if (!isset($arr_hasil[$key]['detail'][$values['id']])) {
                        $arr_hasil[$key]['detail'][$values['id']] = $values;
                    }
                    $arr_hasil[$key]['detail'][$values['id']]['nominal_perbulan'][$v['number']] = $values['nominal'];
                }
            }

            if (!isset($arr_hasil[$key]['total_perbulan'][$v['number']])) {
                if ($key == 'PENDAPATAN_DILUAR_USAHA') {
                    $arr_hasil[$key]['total_perbulan'][$v['number']] = $labarugi['total']['PENDAPATAN DILUAR USAHA'];
                } else if ($key == 'BEBAN_DILUAR_USAHA') {
                    $arr_hasil[$key]['total_perbulan'][$v['number']] = $labarugi['total']['BEBAN DILUAR USAHA'];
                } else {
                    $arr_hasil[$key]['total_perbulan'][$v['number']] = $labarugi['total'][$key];
                }
            }
        }

//        print_die($arr_hasil);
//        $arr_tanggal[$k]['detail'] = $arr;
//        $data['is_detail'] = $params['is_detail'];
    }

//    print_die($arr_tanggal);
//    print_die($arr_hasil);
//    print_die($arr);

    /*
     * isi value kosong, dan mereset array
     */

    $index = 0;
    if (!empty($arr_hasil)) {
        foreach ($arr_hasil as $key => $value) {
            if (!empty($value['detail'])) {
                $arr_hasil[$key]['detail'] = array_values($value['detail']);
            }
        }

        foreach ($arr_hasil as $key => $value) {
            if (!empty($value['detail'])) {
                foreach ($value['detail'] as $k => $v) {
                    foreach ($arr_tanggal as $a => $b) {
                        if (!isset($v['nominal_perbulan'][$b['number']])) {
                            $arr_hasil[$key]['detail'][$k]['nominal_perbulan'][$b['number']] = 0;
                        }
                    }
                }
            }
        }
    }

//    print_die($arr_hasil);

    $data['jumlah_bulan'] = count($arr_tanggal);
    $data['arr_tanggal'] = $arr_tanggal;

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/labaRugi.html', [
            "data" => $data,
            "detail" => $arr,
            "totalsemua" => $data['total'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-laba-rugi.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/labaRugi.html', [
            "data" => $data,
            "detail" => $arr,
            "totalsemua" => $data['total'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr_hasil]);
    }
});
