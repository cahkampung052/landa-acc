<?php

$app->get('/acc/l_arus_kas_custom/getSetting', function ($request, $response) {
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

$app->post('/acc/l_arus_kas_custom/saveSetting', function ($request, $response) {
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

$app->get('/acc/l_arus_kas_custom/laporan', function ($request, $response) {
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

    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' s/d ' . date("d-m-Y", strtotime($tanggal_end));
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

    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

//    $tipe_arus = ["Aktivitas Operasi", "Investasi", "Pendanaan", "Tidak Terklasifikasi"];

    $data_penerimaan = $params;
    $data_penerimaan['tipe'] = 'penerimaan';
    $data_pengeluaran = $params;
    $data_pengeluaran['tipe'] = 'pengeluaran';
    $penerimaan = jurnalKas($data_penerimaan);
    $pengeluaran = jurnalKas($data_pengeluaran);

//    pd($penerimaan);
//    pd($pengeluaran);

//    $akun_merge = array_merge($penerimaan['data']['total_akun']['kredit'], $pengeluaran['data']['total_akun']['debit']);
    $akun_merge_fix = [];

    foreach ($penerimaan['data']['total_akun']['kredit'] as $key => $value) {
        if (isset($akun_merge_fix[$value['akun']['id']])) {
            $akun_merge_fix[$value['akun']['id']]['total'] += $value['total'];
        } else {
            $akun_merge_fix[$value['akun']['id']] = $value;
        }
    }
//    pd($akun_merge_fix);

    foreach ($pengeluaran['data']['total_akun']['debit'] as $key => $value) {
        if (isset($akun_merge_fix[$value['akun']['id']])) {
            $akun_merge_fix[$value['akun']['id']]['total'] -= $value['total'];
        } else {
            $akun_merge_fix[$value['akun']['id']] = $value;
            $akun_merge_fix[$value['akun']['id']]['total'] = ($value['total'] * -1);
        }
    }

//    pd($akun_merge_fix);
//    pd($akun_merge);

    $arr = [];
    $data['saldo_biaya'] = 0;
    foreach ($akun_merge_fix as $key => $value) {
        $value['akun']['tipe_arus'] = !empty($value['akun']['tipe_arus']) ? $value['akun']['tipe_arus'] : 'Tidak Terklasifikasi';
        $tipe = $value['total'] < 0 ? 'PENGELUARAN' : 'PENERIMAAN';
//        $value['total'] = $value['akun']['saldo_normal'] == 1 ? ($value['total'] * -1) : $value['total'];

        if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['id']])) {
            $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['id']]['total'] += $value['total'];
        } else {
            $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['id']]['total'] = $value['total'];
            $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['id']]['akun'] = $value['akun'];
        }

        if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'])) {
            $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'] += $value['total'];
        } else {
            $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'] = $value['total'];
        }

        if (isset($arr[$value['akun']['tipe_arus']]['total'])) {
            $arr[$value['akun']['tipe_arus']]['total'] += $value['total'];
        } else {
            $arr[$value['akun']['tipe_arus']]['total'] = $value['total'];
        }

        if ($value['akun']['tipe_arus'] != 'Tidak Terklasifikasi') {
            $data['saldo_biaya'] += $value['total'];
        }
    }

//    pd($arr);

    $akun_merge_kas = [];
    $index = 0;
    foreach ($penerimaan['data']['total_akun']['debit'] as $key => $value) {
        $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
        $akun_merge_kas[$index]['debit'] = $value['total'];
        $akun_merge_kas[$index]['kredit'] = 0;
        $index++;
    }
    foreach ($pengeluaran['data']['total_akun']['kredit'] as $key => $value) {
        $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
        $akun_merge_kas[$index]['debit'] = 0;
        $akun_merge_kas[$index]['kredit'] = $value['total'];
        $index++;
    }

    /*
     * saldo awal & akhir
     */
    $akun_kas = $db->select("*")->from("acc_m_akun")->where("is_kas", "=", 1)->where("is_deleted", "=", 0)->where("is_tipe", "=", 0)->findAll();
    $arrAkun = [];
    $arrAwal = [];
    $arrPeriode = [];
    $arrAkhir = [];
    foreach ($akun_kas as $key => $value) {
        $arrAwal['detail'][$value->id] = (array) $value;
        $arrPeriode['detail'][$value->id] = (array) $value;
        $arrAkhir['detail'][$value->id] = (array) $value;
        $arrAkun[] = $value->id;
    }

//    pd($arrAwal);


    $arrAkun = implode(", ", $arrAkun);

    $db->select("debit, kredit, m_akun_id")->from("acc_trans_detail")
            ->customWhere("m_akun_id IN($arrAkun)", "AND")
            ->where("date(tanggal)", "<", $tanggal_start);

    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->customWhere("acc_trans_detail.m_lokasi_jurnal_id IN($lokasiId)", "AND");
    }
    $saldo_awal = $db->findAll();

    $db->select("debit, kredit, m_akun_id")->from("acc_trans_detail")
            ->customWhere("m_akun_id IN($arrAkun)", "AND")
            ->where("date(tanggal)", "<=", $tanggal_end);

    if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
        $db->customWhere("acc_trans_detail.m_lokasi_jurnal_id IN($lokasiId)", "AND");
    }

    $saldo_akhir = $db->findAll();

//    pd($saldo_awal);

    foreach ($saldo_awal as $key => $value) {
        if (isset($arrAwal['detail'][$value->m_akun_id]['debit'])) {
            $arrAwal['detail'][$value->m_akun_id]['debit'] += $value->debit;
            $arrAwal['detail'][$value->m_akun_id]['kredit'] += $value->kredit;
            $arrAwal['detail'][$value->m_akun_id]['total'] += $value->debit - $value->kredit;
        } else {
            $arrAwal['detail'][$value->m_akun_id]['debit'] = $value->debit;
            $arrAwal['detail'][$value->m_akun_id]['kredit'] = $value->kredit;
            $arrAwal['detail'][$value->m_akun_id]['total'] = $value->debit - $value->kredit;
        }

        if (isset($arrAwal['total'])) {
            $arrAwal['total'] += $value->debit - $value->kredit;
        } else {
            $arrAwal['total'] = $value->debit - $value->kredit;
        }
    }

    foreach ($akun_merge_kas as $key => $value) {
        if (isset($arrPeriode['detail'][$value['m_akun_id']]['debit'])) {
            $arrPeriode['detail'][$value['m_akun_id']]['debit'] += $value['debit'];
            $arrPeriode['detail'][$value['m_akun_id']]['kredit'] += $value['kredit'];
            $arrPeriode['detail'][$value['m_akun_id']]['total'] += $value['debit'] - $value['kredit'];
        } else {
            $arrPeriode['detail'][$value['m_akun_id']]['debit'] = $value['debit'];
            $arrPeriode['detail'][$value['m_akun_id']]['kredit'] = $value['kredit'];
            $arrPeriode['detail'][$value['m_akun_id']]['total'] = $value['debit'] - $value['kredit'];
        }

        if (isset($arrPeriode['total'])) {
            $arrPeriode['total'] += $value['debit'] - $value['kredit'];
        } else {
            $arrPeriode['total'] = $value['debit'] - $value['kredit'];
        }
    }

    foreach ($saldo_akhir as $key => $value) {
        if (isset($arrAkhir['detail'][$value->m_akun_id]['debit'])) {
            $arrAkhir['detail'][$value->m_akun_id]['debit'] += $value->debit;
            $arrAkhir['detail'][$value->m_akun_id]['kredit'] += $value->kredit;
            $arrAkhir['detail'][$value->m_akun_id]['total'] += $value->debit - $value->kredit;
        } else {
            $arrAkhir['detail'][$value->m_akun_id]['debit'] = $value->debit;
            $arrAkhir['detail'][$value->m_akun_id]['kredit'] = $value->kredit;
            $arrAkhir['detail'][$value->m_akun_id]['total'] = $value->debit - $value->kredit;
        }

        if (isset($arrAkhir['total'])) {
            $arrAkhir['total'] += $value->debit - $value->kredit;
        } else {
            $arrAkhir['total'] = $value->debit - $value->kredit;
        }
    }

    $data['saldo_awal'] = $arrAwal;
    $data['saldo_periode'] = $arrPeriode;
    $data['saldo_akhir'] = $arrAkhir;

//    pd($akun_kas);
//    pd($arr);

    ksort($arr);
    
    foreach ($arr as $key => $value) {
        ksort($arr[$key]['detail']);
    }

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustom.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-arus-kas.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustom.html', [
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
