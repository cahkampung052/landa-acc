<?php

$app->get('/acc/l_aktivitas_keuangan/laporan', function ($request, $response) {
    $params = $request->getParams();
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

    $tanggal2 = new DateTime($filter['tanggal2']);
    $tanggal2->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $tanggal2 = $tanggal2->format("Y-m-d");
    /**
     * Ambil akun laba rugi
     */
    $labarugi = getPemetaanAkun("Laba Rugi Berjalan");
    $akunLabaRugi = isset($labarugi[0]) ? $labarugi[0] : 0;
    $saldoLabaRugi = getLabaRugiNominal(null, $tanggal, null);
    $totalLabaRugi = $saldoLabaRugi["total"];

    $labarugi2 = getPemetaanAkun("Laba Rugi Berjalan");
    $akunLabaRugi2 = isset($labarugi[0]) ? $labarugi[0] : 0;
    $saldoLabaRugi2 = getLabaRugiNominal(null, $tanggal2, null);
    $totalLabaRugi2 = $saldoLabaRugi2["total"];
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
    $totalHarta2 = 0;
    $totalSub = 0;
    $arrHarta = [];
    foreach ($modelHarta as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }

        $getsaldoawal = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal)->find();
        $getsaldoawal2 = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal2)->find();

        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        $saldoAwal2 = (intval($getsaldoawal2->debit) - intval($getsaldoawal2->kredit)) * $val->saldo_normal;

        if ($val->id == $akunLabaRugi) {
            $saldoAwal += $totalLabaRugi;
        }
        if ($val->id == $akunLabaRugi) {
            $saldoAwal2 += $totalLabaRugi2;
        }

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo2 = $saldoAwal2;
        $val->saldo_rp = $val->saldo; $val->saldo_rp2 = $val->saldo2;
        if (($val->saldo < 0 || $val->saldo > 0) || ($val->saldo2 < 0 || $val->saldo2 > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrHarta[$id]['id'] = $id;
                $arrHarta[$id]['kode'] = $val->nama;
                $arrHarta[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $id = $val->id;
                $arrHarta[$val->parent_id]['detail'][] = (array) $val;
                $totalHarta += $val->saldo;
                $totalHarta2 += $val->saldo2;
            }
        }
    }
    foreach ($arrHarta as $key => $val) {
        $arrHarta[$key] = (array) $val;
        $total = 0;
        $total2 = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                    $total2 += $vals['saldo2'];
                }
            }
        }
        if (($total > 0 || $total < 0) || ($total2 > 0 || $total2 < 0)) {
            $arrHarta[$key]['total'] = $total;
            $arrHarta[$key]['total2'] = $total2;
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
    $totalKewajiban2 = 0;
    $arrKewajiban = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
//        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);

        $getsaldoawal = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal)->find();
        $getsaldoawal2 = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal2)->find();

        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        $saldoAwal2 = (intval($getsaldoawal2->debit) - intval($getsaldoawal2->kredit)) * $val->saldo_normal;

        if ($val->id == $akunLabaRugi) {
            $saldoAwal += $totalLabaRugi;
            $saldoAwal2 += $totalLabaRugi2;
        }

        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;

        $val->saldo = $saldoAwal;
        $val->saldo2 = $saldoAwal2;

        $val->saldo_rp = $val->saldo; $val->saldo_rp2 = $val->saldo2;
        if (($val->saldo < 0 || $val->saldo > 0) || ($val->saldo2 < 0 || $val->saldo2 > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrKewajiban[$id]['id'] = $id;
                $arrKewajiban[$id]['kode'] = $val->kode;
                $arrKewajiban[$id]['nama'] = $val->kode . " - " . $val->nama;
            } else {
                $arrKewajiban[$val->parent_id]['detail'][] = (array) $val;
                $arrKewajiban[$val->parent_id]['total'] = (isset($arrKewajiban[$val->parent_id]['total']) ? $arrKewajiban[$val->parent_id]['total'] : 0) + $val->saldo;
                $arrKewajiban[$val->parent_id]['total2'] = (isset($arrKewajiban[$val->parent_id]['total2']) ? $arrKewajiban[$val->parent_id]['total2'] : 0) + $val->saldo2;
                $totalKewajiban += $val->saldo;
                $totalKewajiban2 += $val->saldo2;
            }
        }
    }
    foreach ($arrKewajiban as $key => $val) {
        $arrKewajiban[$key] = (array) $val;
        $total = 0;
        $total2 = 0;
        if (isset($val['detail'])) {
            if (count($val['detail']) > 0) {
                foreach ($val['detail'] as $vals) {
                    $total += $vals['saldo'];
                    $total2 += $vals['saldo2'];
                }
            }
        }
        if (($total > 0 || $total < 0) || ($total2 > 0 || $total2 < 0)) {
            $arrKewajiban[$key]['total'] = $total;
            $arrKewajiban[$key]['total2'] = $total2;
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
    $totalModal2 = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
//        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal)->find();
        $getsaldoawal2 = $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal2)->find();

        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        $saldoAwal2 = (intval($getsaldoawal2->debit) - intval($getsaldoawal2->kredit)) * $val->saldo_normal;

        if ($val->id == $akunLabaRugi) {
            $saldoAwal += $totalLabaRugi;
            $saldoAwal2 += $totalLabaRugi2;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;

        $val->saldo = $saldoAwal;
        $val->saldo2 = $saldoAwal2;

        $val->saldo_rp = $val->saldo; $val->saldo_rp2 = $val->saldo2;
        if (($val->saldo < 0 || $val->saldo > 0) || ($val->saldo2 < 0 || $val->saldo2 > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrModal[$id]['id'] = $id;
                $arrModal[$id]['kode'] = $val->kode;
                $arrModal[$id]['nama'] = $val->kode . ' - ' . $val->nama;
            } else {
                $arrModal[$val->parent_id]['detail'][] = (array) $val;
                $arrModal[$val->parent_id]['total'] = (isset($arrModal[$val->parent_id]['total']) ? $arrModal[$val->parent_id]['total'] : 0) + $val->saldo;
                $arrModal[$val->parent_id]['total2'] = (isset($arrModal[$val->parent_id]['total2']) ? $arrModal[$val->parent_id]['total2'] : 0) + $val->saldo2;
                $totalModal += $val->saldo;
                $totalModal2 += $val->saldo2;
            }
        }
    }
    foreach ($arrModal as $key => $val) {
        $arrModal[$key] = (array) $val;
        $total = 0;
        $total2 = 0;
        if (isset($val['detail'])) {
            foreach ($val['detail'] as $vals) {
                $total += $vals['saldo'];
                $total2 += $vals['saldo2'];
            }
        }
        if (($total > 0 || $total < 0) || ($total2 > 0 || $total2 < 0)) {
            $arrModal[$key]['total'] = $total;
            $arrModal[$key]['total2'] = $total2;
        } else {
            unset($arrModal[$key]);
        }
    }
    $totalKewajibanModal = $totalKewajiban + $totalModal;
    $totalKewajibanModal2 = $totalKewajiban2 + $totalModal2;
    /*
     * end proses harta
     */
    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/aktivitasKeuangan.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
                "total2" => $totalHarta2,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
                "total2" => $totalKewajiban2,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "total2" => $totalModal2,
                "labarugi" => $totalLabaRugi,
                "labarugi2" => $totalLabaRugi2,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
                "total2" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
            "tanggal2" => date("d-m-Y", strtotime($tanggal2)),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-neraca.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/aktivitasKeuangan.html', [
            "modelHarta" =>
                [
                "list" => $arrHarta,
                "total" => $totalHarta,
                "total2" => $totalHarta2,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
                "total2" => $totalKewajiban2,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "total2" => $totalModal2,
                "labarugi" => $totalLabaRugi,
                "labarugi2" => $totalLabaRugi2,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
                "total2" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
            "tanggal2" => date("d-m-Y", strtotime($tanggal2)),
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
                "total2" => $totalHarta2,
            ],
            "modelKewajiban" =>
                [
                "list" => $arrKewajiban,
                "total" => $totalKewajiban,
                "total2" => $totalKewajiban2,
            ],
            "modelModal" =>
                [
                "list" => $arrModal,
                "total" => $totalModal,
                "total2" => $totalModal2,
                "labarugi" => $totalLabaRugi,
                "labarugi2" => $totalLabaRugi2,
            ],
            "modelKewajibanModal" =>
                [
                "total" => $totalKewajibanModal,
                "total2" => $totalKewajibanModal2,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
            "tanggal2" => date("d-m-Y", strtotime($tanggal2)),
            "disiapkan" => date("d-m-Y, H:i"),
        ]);
    }
});
