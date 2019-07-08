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
$app->get('/acc/l_neraca/laporan', function ($request, $response) {
    $params = $request->getParams();
    $filter = $params;
    $db = $this->db;
    /** tanggal */
    $tanggal = new DateTime($filter['tanggal']);
    $tanggal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $tanggal = $tanggal->format("Y-m-d");
    /*
     * ambil child dari harta, kewajiban, modal
     */
    $idHarta = getChildId("acc_m_akun", 1);
    $idKewajiban = getChildId("acc_m_akun", 2);
    $idModal = getChildId("acc_m_akun", 3);
    /*
     * proses harta
     */
    $db->select("
            acc_m_akun.id,
            acc_m_akun.kode,
            acc_m_akun.nama,
            acc_m_akun.level,
            acc_m_akun.is_tipe,
            acc_m_akun.parent_id
            ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.id IN(" . implode(",", $idHarta) . ")");
    $modelHarta = $db->findAll();
    $totalHarta = 0;
    $totalSub = 0;
    $arrHarta = [];
    foreach ($modelHarta as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
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
        $arrHarta[$key]['total'] = $total;
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
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.id IN(" . implode(",", $idKewajiban) . ")");
    $modelKewajiban = $db->findAll();
    $totalKewajiban = 0;
    $arrKewajiban = [];
    foreach ($modelKewajiban as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->saldo = $saldoAwal;
        $val->saldo_rp = $val->saldo;
        if (($val->saldo < 0 || $val->saldo > 0) || $val->is_tipe == 1) {
            if ($val->is_tipe == 1) {
                $id = $val->id;
                $arrKewajiban[$id]['kode'] = $val->kode;
                $arrKewajiban[$id]['nama'] = $val->kode ." - ". $val->nama;
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
        $arrKewajiban[$key]['total'] = $total;
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
        acc_m_akun.parent_id
        ")
            ->from("acc_m_akun")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.kode")
            ->customWhere("acc_m_akun.id IN(" . implode(",", $idModal) . ")");
    $modelModal = $db->findAll();
    $arr = getLabaRugi($tanggal);
    $saldo_labarugi = $arr[0]['total']-$arr[1]['total']-$arr[2]['total']-$arr[3]['total']+$arr[4]['total']-$arr[5]['total'];
    $totalModal = 0;
    $arrModal = [];
    foreach ($modelModal as $key => $val) {
        $db->select("SUM(debit) as debit, SUM(kredit) as kredit")
                ->from("acc_trans_detail")
                ->where('m_akun_id', '=', $val->id)
                ->andWhere('date(tanggal)', '<=', $tanggal);
        $getsaldoawal = $db->find();
        $saldoAwal = intval($getsaldoawal->debit) - intval($getsaldoawal->kredit);
        $val->nama_lengkap = $val->kode . ' - ' . $val->nama;
        $val->laba = '';
        if ($val->nama == 'Laba Tahun Berjalan') {
            $val->laba = $saldo_labarugi > 0 || $saldo_labarugi < 0 ? '(' . $saldo_labarugi . ')' : '';
            $saldoAwal += $saldo_labarugi;
        }
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
        $arrModal[$key]['total'] = $total;
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
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-neraca.xls");
        echo $content;
    } else if (isset($params['print']) && $params['print'] == 1) {
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
