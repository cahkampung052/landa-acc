<?php
$app->get('/acc/l_neraca/laporan', function ($request, $response) {
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
    /**
     * Ambil laba / rugi
     */
    $totalLabaRugi = getLabaRugi("1970-01-01", $tanggal, null, false);
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
            $saldoAwal += $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
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
    $arrKewajiban   = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
        $db->where('m_akun_id', '=', $val->id)->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal   = $db->find();
        $saldoAwal      = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        if ($val->id == $akunLabaRugi) {
            $saldoAwal += $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
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
    $labarugi = getLabaRugi($tanggal);
    $arr = $labarugi['data'];
    $pendapatan = isset($labarugi['total']['PENDAPATAN']) ? $labarugi['total']['PENDAPATAN'] : 0;
    $biaya = isset($labarugi['total']['BIAYA']) ? $labarugi['total']['BIAYA'] : 0;
    $beban = isset($labarugi['total']['BEBAN']) ? $labarugi['total']['BEBAN'] : 0;
    $saldo_labarugi = $pendapatan - $biaya - $beban;
    $totalModal = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)");
        }
        $db->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = (intval($getsaldoawal->debit) - intval($getsaldoawal->kredit)) * $val->saldo_normal;
        if ($val->id == $akunLabaRugi) {
            $saldoAwal += $totalLabaRugi;
        }
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
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
                "labarugi" => $saldo_labarugi,
            ],
            "modelKewajibanModal" =>
            [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
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
                "labarugi" => $saldo_labarugi,
            ],
            "modelKewajibanModal" =>
            [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
            "disiapkan" => date("d-m-Y, H:i"),
            "is_detail" => $filter['is_detail'],
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
//        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
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
                "labarugi" => $saldo_labarugi,
            ],
            "modelKewajibanModal" =>
            [
                "total" => $totalKewajibanModal,
            ],
            "tanggal" => date("d-m-Y", strtotime($tanggal)),
            "disiapkan" => date("d-m-Y, H:i")
        ]);
    }
});
