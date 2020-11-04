<?php

$app->get('/acc/l_neraca/laporan', function ($request, $response) {
    $params = $request->getParams();
    $data['img'] = imgLaporan();
    $filter = $params;
    $db = $this->db;
    /*
     * lokasi
     */
    if (isset($filter['m_lokasi_id']) && !empty($filter["m_lokasi_id"])) {
        $lokasiId = getChildId("acc_m_lokasi", $filter['m_lokasi_id']);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $filter['m_lokasi_id']);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $filter['m_lokasi_id'];
        }
    }
    /**
     * tanggal
     */
    $tanggal = new DateTime($filter['tanggal']);
    $tanggal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $tanggal = $tanggal->format("Y-m-d");
    /**
     * Ambil akun laba rugi
     */
    $labarugi = getPemetaanAkun("Laba Rugi Berjalan");
    $akunLabaRugi = isset($labarugi[0]) ? $labarugi[0] : 0;
    $saldoLabaRugi = getLabaRugiNominal(null, $tanggal, null);
    $totalLabaRugi = $saldoLabaRugi["total"];
    /*
     * ambil akun pengecualian
     */
    $akunPengecualian = getMasterSetting();
    $arrPengecualian = [];
    if (is_array($akunPengecualian) && !empty($akunPengecualian)) {
        foreach ($akunPengecualian->pengecualian_neraca as $a => $b) {
            array_push($arrPengecualian, $b->m_akun_id->id);
        }
    }
    /*
     * proses harta
     */
    $db->select("
            acc_m_akun.id,
            acc_m_akun.kode,
            acc_m_akun.nama,
            acc_m_akun.level,
            acc_m_akun.is_tipe,
            acc_m_akun.parent_id,
            acc_m_akun.saldo_normal
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode");
    if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
        $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
    }
    $db->where("tipe", "=", "HARTA");
    $modelHarta = $db->findAll();
    $totalHarta = 0;
    $totalSub = 0;
    $arrHarta = [];
    foreach ($modelHarta as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        if ($val->id == $akunLabaRugi) {
            $saldoAwal = $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrHarta[$id]['id'] = $id;
                $arrHarta[$id]['kode'] = $val->nama;
                $arrHarta[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $id = $val->id;
                $arrHarta[$val->parent_id]['detail'][] = (array) $val;
                $totalHarta += $val->saldo;
            }
        }
    }
    foreach ($arrHarta as $key => $val) {
        $arrHarta[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }
        if ($total > 0 || $total < 0) {
            $arrHarta[$key]['total'] = $total;
        } else {
            unset($arrHarta[$key]);
        }
    }
    /*
     * end proses harta
     */
    /*
     * proses kewajiban
     */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id,
        acc_m_akun.saldo_normal
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode");
    if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
        $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
    }
    $db->where("tipe", "=", "KEWAJIBAN");
    $modelKewajiban = $db->findAll();
    $totalKewajiban = 0;
    $arrKewajiban = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        if ($val->id == $akunLabaRugi) {
            $saldoAwal = $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrKewajiban[$id]['id'] = $id;
                $arrKewajiban[$id]['kode'] = $val->kode;
                $arrKewajiban[$id]['nama'] = $val->kode . " - " . $val->nama;
            } else {
                $arrKewajiban[$val->parent_id]['detail'][] = (array) $val;
                $arrKewajiban[$val->parent_id]['total'] = (isset($arrKewajiban[$val->parent_id]['total']) ? $arrKewajiban[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalKewajiban += $val->saldo;
            }
        }
    }
    foreach ($arrKewajiban as $key => $val) {
        $arrKewajiban[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                }
            }
        }
        if ($total > 0 || $total < 0) {
            $arrKewajiban[$key]['total'] = $total;
        } else {
            unset($arrKewajiban[$key]);
        }
    }
    /*
     * end proses kewajiban
     */
    /*
     * proses modal
     */
    $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id,
        acc_m_akun.saldo_normal
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode");
    if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
        $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
    }
    $db->where("tipe", "=", "MODAL");
    $modelModal = $db->findAll();
    /*
     * panggil function saldo laba rugi, karena digunakan juga di laporan neraca
     */
    $totalModal = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        if ($val->id == $akunLabaRugi) {
            $saldoAwal = $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrModal[$id]['id'] = $id;
                $arrModal[$id]['kode'] = $val->kode;
                $arrModal[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $arrModal[$val->parent_id]['detail'][] = (array) $val;
                $arrModal[$val->parent_id]['total'] = (isset($arrModal[$val->parent_id]['total']) ? $arrModal[$val->parent_id]['total'] : 0) + $val->saldo;
                $totalModal += $val->saldo;
            }
        }
    }
    foreach ($arrModal as $key => $val) {
        $arrModal[$key] = (array) $val;
        $total = 0;
        if (isset($val['detail'])) {
            foreach ($val['detail'] as $vals) {
                $total += $vals['saldo'];
            }
        }
        if ($total > 0 || $total < 0) {
            $arrModal[$key]['total'] = $total;
        } else {
            unset($arrModal[$key]);
        }
    }
    $totalKewajibanModal = $totalKewajiban + $totalModal;
    /*
     * end proses harta
     */
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neraca.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-neraca.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neraca.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i")
        ]);
    }
})->setName('lNeraca');

$app->get('/acc/l_neraca/laporan_periode', function ($request, $response) {
    $params = $request->getParams();
    $data['img'] = imgLaporan();
    $filter = $params;
    $db = $this->db;

//    print_die($params);

    /*
     * lokasi
     */
    if (isset($filter['m_lokasi_id']) && !empty($filter["m_lokasi_id"])) {
        $lokasiId = getChildId("acc_m_lokasi", $filter['m_lokasi_id']);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $filter['m_lokasi_id']);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $filter['m_lokasi_id'];
        }
    }
    /**
     * tanggal
     */
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");

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

    /**
     * Ambil akun laba rugi
     */
    $labarugi = getPemetaanAkun("Laba Rugi Berjalan");
    $akunLabaRugi = isset($labarugi[0]) ? $labarugi[0] : 0;

    /*
     * ambil akun pengecualian
     */
    $akunPengecualian = getMasterSetting();
    $arrPengecualian = [];
    if (is_array($akunPengecualian) && !empty($akunPengecualian)) {
        foreach ($akunPengecualian->pengecualian_neraca as $a => $b) {
            array_push($arrPengecualian, $b->m_akun_id->id);
        }
    }

    $arrHarta = [];
    $arrKewajiban = [];
    $arrModal = [];

    $totalHarta = [];
    $totalKewajiban = [];
    $totalModal = [];
    $totalLabaRugi2 = [];
    $totalKewajibanModal2 = [];
    foreach ($arr_tanggal as $a => $b) {

        $tanggal = $b['akhir'];
        $saldoLabaRugi = getLabaRugiNominal(null, $b['akhir'], null);
        $totalLabaRugi = $saldoLabaRugi["total"];
        $totalLabaRugi2[$b['number']] = $saldoLabaRugi["total"];

        /*
         * proses harta
         */
        $db->select("
            acc_m_akun.id,
            acc_m_akun.kode,
            acc_m_akun.nama,
            acc_m_akun.level,
            acc_m_akun.is_tipe,
            acc_m_akun.parent_id,
            acc_m_akun.saldo_normal
        ")
                ->from("acc_m_akun")
                ->groupBy("acc_m_akun.id")
                ->orderBy("acc_m_akun.kode");
        if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
            $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
        }
        $db->where("tipe", "=", "HARTA");
        $modelHarta = $db->findAll();

        $totalSub = 0;

        foreach ($modelHarta as $key => $val) {
            $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail");
            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
            }
            $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
            $getsaldoawal = $db->find();
            $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
            if ($val->id == $akunLabaRugi) {
                $saldoAwal = $totalLabaRugi;
            }
            $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
            $val->saldo = $saldoAwal;
            $val->saldo_rp = $val->saldo;
            if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
                if ($val->is_tipe == 1) {
                    $id = $val->id;
                    $arrHarta[$id]['id'] = $id;
                    $arrHarta[$id]['kode'] = $val->nama;
                    $arrHarta[$id]['nama'] = $val->kode . ' - ' . $val->nama;
                } else {
                    $id = $val->id;
                    if (!isset($arrHarta[$val->parent_id]['detail'][$id])) {
                        $arrHarta[$val->parent_id]['detail'][$id] = (array) $val;
                    }
                    $arrHarta[$val->parent_id]['detail'][$id]['saldo2'][$b['number']] = $val->saldo;
                    $arrHarta[$val->parent_id]['detail'][$id]['saldo_rp2'][$b['number']] = $val->saldo_rp;
                    if (isset($totalHarta[$b['number']])) {
                        $totalHarta[$b['number']] += $val->saldo;
                    } else {
                        $totalHarta[$b['number']] = $val->saldo;
                    }
                }
            }
        }
        foreach ($arrHarta as $key => $val) {
            $arrHarta[$key] = (array) $val;
            $total = 0;
            if (isset($val['detail'])) {
                if (count($val['detail']) > 0) {
                    foreach ($val['detail'] as $vals) {
                        $total += !empty($vals['saldo2'][$b['number']]) ? $vals['saldo2'][$b['number']] : 0;
                    }
                }
            }
            if ($total > 0 || $total < 0) {
                $arrHarta[$key]['total'][$b['number']] = $total;
            } else {
                unset($arrHarta[$key]);
            }
        }
        /*
         * end proses harta
         */
        /*
         * proses kewajiban
         */
        $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id,
        acc_m_akun.saldo_normal
        ")
                ->from("acc_m_akun")
                ->groupBy("acc_m_akun.id")
                ->orderBy("acc_m_akun.kode");
        if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
            $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
        }
        $db->where("tipe", "=", "KEWAJIBAN");
        $modelKewajiban = $db->findAll();

        foreach ($modelKewajiban as $key => $val) {
            $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
            }
            $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
            $getsaldoawal = $db->find();
            $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
            if ($val->id == $akunLabaRugi) {
                $saldoAwal = $totalLabaRugi;
            }
            $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
            $val->saldo = $saldoAwal;
            $val->saldo_rp = $val->saldo;
            if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
                if ($val->is_tipe == 1) {
                    $id = $val->id;
                    $arrKewajiban[$id]['id'] = $id;
                    $arrKewajiban[$id]['kode'] = $val->kode;
                    $arrKewajiban[$id]['nama'] = $val->kode . " - " . $val->nama;
                } else {
                    $id = $val->id;
                    if (!isset($arrKewajiban[$val->parent_id]['detail'][$id])) {
                        $arrKewajiban[$val->parent_id]['detail'][$id] = (array) $val;
                    }
                    $arrKewajiban[$val->parent_id]['detail'][$id]['saldo2'][$b['number']] = $val->saldo;
                    $arrKewajiban[$val->parent_id]['detail'][$id]['saldo_rp2'][$b['number']] = $val->saldo_rp;
                    if (isset($totalKewajiban[$b['number']])) {
                        $totalKewajiban[$b['number']] += $val->saldo;
                    } else {
                        $totalKewajiban[$b['number']] = $val->saldo;
                    }
                }
            }
        }
        foreach ($arrKewajiban as $key => $val) {
            $arrKewajiban[$key] = (array) $val;
            $total = 0;
            if (isset($val['detail'])) {
                if (count($val['detail']) > 0) {
                    foreach ($val['detail'] as $vals) {
                        $total += !empty($vals['saldo2'][$b['number']]) ? $vals['saldo2'][$b['number']] : 0;
                    }
                }
            }
            if ($total > 0 || $total < 0) {
                $arrKewajiban[$key]['total'][$b['number']] = $total;
            } else {
                unset($arrKewajiban[$key]);
            }
        }
        /*
         * end proses kewajiban
         */
        /*
         * proses modal
         */
        $db->select("
        acc_m_akun.id,
        acc_m_akun.kode,
        acc_m_akun.nama,
        acc_m_akun.level,
        acc_m_akun.is_tipe,
        acc_m_akun.parent_id,
        acc_m_akun.saldo_normal
        ")
                ->from("acc_m_akun")
                ->groupBy("acc_m_akun.id")
                ->orderBy("acc_m_akun.kode");
        if (is_array($arrPengecualian) && !empty($arrPengecualian)) {
            $db->customWhere("acc_m_akun.id NOT IN(" . implode(",", $arrPengecualian) . ")");
        }
        $db->where("tipe", "=", "MODAL");
        $modelModal = $db->findAll();
        /*
         * panggil function saldo laba rugi, karena digunakan juga di laporan neraca
         */

        foreach ($modelModal as $key => $val) {
            $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
            }
            $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
            $getsaldoawal = $db->find();
            $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
            if ($val->id == $akunLabaRugi) {
                $saldoAwal = $totalLabaRugi;
            }
            $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
            $val->saldo = $saldoAwal;
            $val->saldo_rp = $val->saldo;
            if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
                if ($val->is_tipe == 1) {
                    $id = $val->id;
                    $arrModal[$id]['id'] = $id;
                    $arrModal[$id]['kode'] = $val->kode;
                    $arrModal[$id]['nama'] = $val->kode . ' - ' . $val->nama;
                } else {
                    $id = $val->id;
                    if (!isset($arrModal[$val->parent_id]['detail'][$id])) {
                        $arrModal[$val->parent_id]['detail'][$id] = (array) $val;
                    }
                    $arrModal[$val->parent_id]['detail'][$id]['saldo2'][$b['number']] = $val->saldo;
                    $arrModal[$val->parent_id]['detail'][$id]['saldo_rp2'][$b['number']] = $val->saldo_rp;
                    if (isset($totalModal[$b['number']])) {
                        $totalModal[$b['number']] += $val->saldo;
                    } else {
                        $totalModal[$b['number']] = $val->saldo;
                    }
                }
            }
        }
        foreach ($arrModal as $key => $val) {
            $arrModal[$key] = (array) $val;
            $total = 0;
            if (isset($val['detail'])) {
                foreach ($val['detail'] as $vals) {
                    $total += !empty($vals['saldo2'][$b['number']]) ? $vals['saldo2'][$b['number']] : 0;
                }
            }
            if ($total > 0 || $total < 0) {
                $arrModal[$key]['total'][$b['number']] = $total;
            } else {
                unset($arrModal[$key]);
            }
        }
//        $totalKewajibanModal = $totalKewajiban + $totalModal;
        $totalKewajibanModal2[$b['number']] = (isset($totalKewajiban[$b['number']]) ? $totalKewajiban[$b['number']] : 0) + (isset($totalModal[$b['number']]) ? $totalModal[$b['number']] : 0);
//        $totalKewajibanModal2[$b['number']] = 0;
        /*
         * end proses harta
         */
    }

//    print_die($arrHarta);
//    print_die($totalHarta);
//    print_die($arrKewajiban);
//    print_die($arrModal);
//    print_die($totalLabaRugi2);
//    print_die($totalKewajibanModal2);
//    print_die($totalModal);

    foreach ($arrHarta as $key => $value) {
        foreach ($value['detail'] as $keys => $values) {
            foreach ($arr_tanggal as $k => $v) {
                if (empty($arrHarta[$key]['detail'][$keys]['saldo2'][$v['number']])) {
                    $arrHarta[$key]['detail'][$keys]['saldo2'][$v['number']] = 0;
                }
                if (empty($arrHarta[$key]['detail'][$keys]['saldo_rp2'][$v['number']])) {
                    $arrHarta[$key]['detail'][$keys]['saldo_rp2'][$v['number']] = 0;
                }
            }
        }
    }

    foreach ($arrModal as $key => $value) {
        foreach ($value['detail'] as $keys => $values) {
            foreach ($arr_tanggal as $k => $v) {
                if (empty($arrModal[$key]['detail'][$keys]['saldo2'][$v['number']])) {
                    $arrModal[$key]['detail'][$keys]['saldo2'][$v['number']] = 0;
                }
                if (empty($arrModal[$key]['detail'][$keys]['saldo_rp2'][$v['number']])) {
                    $arrModal[$key]['detail'][$keys]['saldo_rp2'][$v['number']] = 0;
                }
            }
        }
    }

    foreach ($arrKewajiban as $key => $value) {
        foreach ($value['detail'] as $keys => $values) {
            foreach ($arr_tanggal as $k => $v) {
                if (empty($arrKewajiban[$key]['detail'][$keys]['saldo2'][$v['number']])) {
                    $arrKewajiban[$key]['detail'][$keys]['saldo2'][$v['number']] = 0;
                }
                if (empty($arrKewajiban[$key]['detail'][$keys]['saldo_rp2'][$v['number']])) {
                    $arrKewajiban[$key]['detail'][$keys]['saldo_rp2'][$v['number']] = 0;
                }
            }
        }
    }

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neracaPeriode.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi2,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
            "jumlah_tanggal" => count($arr_tanggal),
            "arr_tanggal" => $arr_tanggal,
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-neraca.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/neracaPeriode.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
            "jumlah_tanggal" => count($arr_tanggal),
            "arr_tanggal" => $arr_tanggal,
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
    } else {
        return successResponse($response, [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "labarugi" => $totalLabaRugi,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d M Y", strtotime($tanggal)),
            "lokasi" => $params['lokasi_nama'],
            "img" => imgLaporan(),
            "disiapkan" => date("d-m-Y, H:i"),
            "jumlah_tanggal" => count($arr_tanggal),
            "arr_tanggal" => $arr_tanggal,
        ]);
    }
});
