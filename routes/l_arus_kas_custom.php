<?php

$app->get('/l_arus_kas_custom/getSetting', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

    $models = $db->select("acc_m_setting_arus_kas.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
                    ->from('acc_m_setting_arus_kas')
                    ->join("LEFT JOIN", "acc_m_akun", "acc_m_akun.id = acc_m_setting_arus_kas.m_akun_id")
                    ->orderBy("id")->findAll();

    $arr = [];
    foreach ($models as $key => $value) {
        if (!empty($value->m_akun_id)) {
            $akun = implode(", ", json_decode($value->m_akun_id));
            $value->akun = !empty($value->m_akun_id) ? $db->select("id, kode, nama")->from("acc_m_akun")->customWhere("id IN($akun)", "AND")->findAll() : [];
        }

        $arr[$value->tipe]['tipe'] = $value->tipe;
        $arr[$value->tipe]['tipe'] = $value->tipe;
        $arr[$value->tipe]['detail'][] = (array) $value;
    }

    return successResponse($response, ['data' => $arr, 'status' => !empty($arr) ? true : false]);
});

$app->post('/l_arus_kas_custom/saveSetting', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

//    print_r($params);
//    die;

    try {
        $delete = true;
        $id = 1;
        foreach ($params['form'] as $key => $value) {
            if (isset($value['detail']) && !empty($value['detail'])) {
                if ($delete) {
                    $db->run("DELETE FROM acc_m_setting_arus_kas");
                    $delete = false;
                }
                foreach ($value['detail'] as $k => $v) {
                    $data = [];
                    $data['id'] = $id;
                    $data['tipe'] = $value['tipe'];
                    $data['nama'] = $v['nama'];
                    if (isset($v['akun'][0]) && !empty($v['akun'][0])) {
                        $akunId = [];
                        foreach ($v['akun'] as $a => $b) {
                            $akunId[] = $b['id'];
                        }
                        $data['m_akun_id'] = json_encode($akunId);
                    }
                    $data['is_total'] = isset($v['is_total']) ? $v['is_total'] : 0;
                    $db->insert("acc_m_setting_arus_kas", $data);
                    $id++;
                }
            }
        }
        return successResponse($response, []);
    } catch (Exception $exc) {
        return unprocessResponse($response, ["terjadi masalah pada server"]);
    }
});

$app->get('/l_arus_kas_custom/laporan', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

//    pd($params);
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

    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' Sampai ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

    $akun_custom = $db->select("*")->from("acc_m_setting_arus_kas")->orderBy("id")->findAll();

    $arr = [];
    $akunId = [];
    foreach ($akun_custom as $key => $value) {
        $temp_akun = [];
        if (!empty($value->m_akun_id)) {

            foreach (json_decode($value->m_akun_id) as $k => $v) {
                $akun = getChildId('acc_m_akun', $v);
                if (!empty($akun)) {
                    foreach ($akun as $x => $y) {
                        $akunId[] = $y;
                        $temp_akun[] = $y;
                    }
                } else {
                    $temp_akun[] = $v;
                }
            }
        }
        $arr[$value->tipe]['tipe'] = $value->tipe;
        $value->akun = $temp_akun;
        $arr[$value->tipe]['detail'][] = (array) $value;
    }
    $akunId = implode(", ", $akunId);

//    pd($akunId);
//    pd($arr);

    $db->select("m_akun_id, debit, kredit")->from("acc_trans_detail")
            ->customWhere("m_akun_id IN($akunId)", "AND")
            ->andWhere('date(tanggal)', '<', $tanggal_start);

    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->customWhere("m_lokasi_id IN($lokasiId)", "AND");
    }

    $trans_detail_awal = $db->findAll();

    $db->select("m_akun_id, debit, kredit")->from("acc_trans_detail")
            ->customWhere("m_akun_id IN($akunId)", "AND")
            ->andWhere('date(tanggal)', '>=', $tanggal_start)
            ->andWhere('date(tanggal)', '<=', $tanggal_end);

    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->customWhere("m_lokasi_id IN($lokasiId)", "AND");
    }

    $trans_detail = $db->findAll();

//    pd($trans_detail);

    $temp_arr_awal = [];
    foreach ($trans_detail_awal as $key => $value) {
        if (isset($temp_arr_awal[$value->m_akun_id])) {
            $temp_arr_awal[$value->m_akun_id] += $value->debit - $value->kredit;
        } else {
            $temp_arr_awal[$value->m_akun_id] = $value->debit - $value->kredit;
        }
    }

    $temp_arr = [];
    foreach ($trans_detail as $key => $value) {
        if (isset($temp_arr[$value->m_akun_id])) {
            $temp_arr[$value->m_akun_id] += $value->debit - $value->kredit;
        } else {
            $temp_arr[$value->m_akun_id] = $value->debit - $value->kredit;
        }
    }

//    pd($temp_arr);

    foreach ($arr as $key => $value) {
        $total = 0;
        $total_awal = 0;
        foreach ($value['detail'] as $k => $v) {
            if (!empty($v['akun'])) {
                foreach ($v['akun'] as $x => $y) {
                    if (isset($arr[$key]['detail'][$k]['total']) && !empty($arr[$key]['detail'][$k]['total'])) {
                        $arr[$key]['detail'][$k]['total'] += isset($temp_arr[$y]) && !empty($temp_arr[$y]) ? $temp_arr[$y] : 0;
                    } else {
                        $arr[$key]['detail'][$k]['total'] = isset($temp_arr[$y]) && !empty($temp_arr[$y]) ? $temp_arr[$y] : 0;
                    }

                    if (isset($arr[$key]['detail'][$k]['total_awal']) && !empty($arr[$key]['detail'][$k]['total_awal'])) {
                        $arr[$key]['detail'][$k]['total_awal'] += isset($temp_arr_awal[$y]) && !empty($temp_arr_awal[$y]) ? $temp_arr_awal[$y] * -1 : 0;
                    } else {
                        $arr[$key]['detail'][$k]['total_awal'] = isset($temp_arr_awal[$y]) && !empty($temp_arr_awal[$y]) ? $temp_arr_awal[$y] * -1 : 0;
                    }

                    if (isset($arr[$key]['detail'][$k]['total_akhir']) && !empty($arr[$key]['detail'][$k]['total_akhir'])) {
                        $a = isset($temp_arr_awal[$y]) && !empty($temp_arr_awal[$y]) ? $temp_arr_awal[$y] * -1 : 0;
                        $b = isset($temp_arr[$y]) && !empty($temp_arr[$y]) ? $temp_arr[$y] : 0;
                        $arr[$key]['detail'][$k]['total_akhir'] += $a - $b;
                    } else {
                        $a = isset($temp_arr_awal[$y]) && !empty($temp_arr_awal[$y]) ? $temp_arr_awal[$y] * -1 : 0;
                        $b = isset($temp_arr[$y]) && !empty($temp_arr[$y]) ? $temp_arr[$y] : 0;
                        $arr[$key]['detail'][$k]['total_akhir'] = $a - $b;
                    }

                    $total += isset($temp_arr[$y]) && !empty($temp_arr[$y]) ? $temp_arr[$y] : 0;
                    $total_awal += isset($temp_arr_awal[$y]) && !empty($temp_arr_awal[$y]) ? $temp_arr_awal[$y] * -1 : 0;
                }
            } else {
                $arr[$key]['detail'][$k]['total'] = 0;
                $arr[$key]['detail'][$k]['total_awal'] = 0;
                $arr[$key]['detail'][$k]['total_akhir'] = 0;
            }

            if ($v['is_total'] == 1) {
                $arr[$key]['detail'][$k]['total'] = $total;
                $arr[$key]['total'] = $total;
                $arr[$key]['detail'][$k]['total_awal'] = $total_awal;
                $arr[$key]['detail'][$k]['total_akhir'] = $total_awal - $total;
                $arr[$key]['total_awal'] = $total_awal;
                $arr[$key]['total_akhir'] = $total_awal - $total;
                $total = 0;
                $total_awal = 0;
            }
        }
    }

//    pd($arr);

    $data['total'] = 0;
    $data['total_awal'] = 0;
    foreach ($arr as $key => $value) {
        $data['total'] += $value['total_akhir'];
        $data['total_awal'] += $value['total_awal'];
    }

//    pd($data);

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigView();
        $content = $view->fetch('laporan/arus_kas.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-arus-kas.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigView();
        $content = $view->fetch('laporan/arus_kas.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
});
