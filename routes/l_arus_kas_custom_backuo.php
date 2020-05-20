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

    $akun_custom = $db->select("*")->from("acc_m_setting_arus_kas")->orderBy("id")->findAll();

//    pd($akun_custom);

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
//    $akunId = implode(", ", $akunId);
//    pd($akunId);
//    pd($arr);

    $data_penerimaan = $params;
    $data_penerimaan['tipe'] = 'penerimaan';
    $data_pengeluaran = $params;
    $data_pengeluaran['tipe'] = 'pengeluaran';
    $penerimaan = jurnalKas($data_penerimaan);
    $pengeluaran = jurnalKas($data_pengeluaran);

    $akun_merge = [];
    $index = 0;
    foreach ($penerimaan['data']['total_akun']['kredit'] as $key => $value) {
        $akun_merge[$index]['m_akun_id'] = $value['akun']['id'];
        $akun_merge[$index]['akun'] = $value['akun'];
        $akun_merge[$index]['total'] = $value['total'];
        $akun_merge[$index]['tipe'] = 'penerimaan';
        $index++;
    }
    foreach ($pengeluaran['data']['total_akun']['debit'] as $key => $value) {
        $akun_merge[$index]['m_akun_id'] = $value['akun']['id'];
        $akun_merge[$index]['akun'] = $value['akun'];
        $akun_merge[$index]['tipe'] = 'pengeluaran';
        $akun_merge[$index]['total'] = $value['total'];
        $index++;
    }

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

//    pd($akun_merge_kas);
//    pd($akun_merge);
//    pd($penerimaan);
    $data['saldo_biaya'] = 0;
    foreach ($arr as $key => $value) {
        $total = 0;
        foreach ($value['detail'] as $keys => $values) {
//            if ($values['nama'] == "PENERIMAAN") {
//                foreach ($penerimaan['data']['total_akun']['kredit'] as $k => $v) {
//                    if (in_array($v['akun']['id'], $arr[$key]['detail'][$keys]['akun'])) {
//                        if (isset($arr[$key]['detail'][$keys]['detail'][$v['akun']['id']])) {
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] += $v['total'];
//                        } else {
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['akun'] = $v['akun'];
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['nama'] = $v['akun']['nama'];
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] = $v['total'];
//                        }
//
//                        if (isset($arr[$key]['detail'][$keys]['total'])) {
//                            $arr[$key]['detail'][$keys]['total'] += $v['total'];
//                        } else {
//                            $arr[$key]['detail'][$keys]['total'] = $v['total'];
//                        }
//                        $total += $v['total'];
//                    }
//                }
//            } else if ($values['nama'] == "PENGELUARAN") {
//                foreach ($pengeluaran['data']['total_akun']['debit'] as $k => $v) {
//                    if (in_array($v['akun']['id'], $arr[$key]['detail'][$keys]['akun'])) {
//                        if (isset($arr[$key]['detail'][$keys]['detail'][$v['akun']['id']])) {
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] += $v['total'];
//                        } else {
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['akun'] = $v['akun'];
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['nama'] = $v['akun']['nama'];
//                            $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] = $v['total'];
//                        }
//
//                        if (isset($arr[$key]['detail'][$keys]['total'])) {
//                            $arr[$key]['detail'][$keys]['total'] += $v['total'];
//                        } else {
//                            $arr[$key]['detail'][$keys]['total'] = $v['total'];
//                        }
//                        $total += $v['total'];
//                    }
//                }
//            }
            foreach ($akun_merge as $k => $v) {
                if (in_array($v['akun']['id'], $arr[$key]['detail'][$keys]['akun'])) {
                    if (isset($arr[$key]['detail'][$keys]['detail'][$v['akun']['id']])) {
                        $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] += $v['total'];
                    } else {
                        $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['akun'] = $v['akun'];
                        $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['nama'] = $v['akun']['nama'];
                        $arr[$key]['detail'][$keys]['detail'][$v['akun']['id']]['total'] = $v['total'];
                    }

                    if (isset($arr[$key]['detail'][$keys]['total'])) {
                        $arr[$key]['detail'][$keys]['total'] += $v['total'];
                    } else {
                        $arr[$key]['detail'][$keys]['total'] = $v['total'];
                    }
                    $total += $v['tipe'] == 'penerimaan' ? $v['total'] : ($v['total'] * -1);
                    $data['saldo_biaya'] += $v['tipe'] == 'penerimaan' ? $v['total'] : ($v['total'] * -1);
                }
            }


            if ($values['is_total'] == 1) {
                $arr[$key]['detail'][$keys]['total'] = $total;
                $total = 0;
            }
        }
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
