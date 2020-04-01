<?php
$app->get('/acc/l_rekap_piutang/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql    = $this->db;
    /**
     * tanggal awal
     */
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * tanggal akhir
     */
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));
    /**
     * Format Tanggal
     */
    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");
    /**
     * Siapkan sub header laporan
     */
    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = (isset($params['nama_lokasi']) && !empty($params['nama_lokasi'])) ? $params['nama_lokasi'] : "Semua";
    $data['akun'] = $params['nama_akun'];
    /*
     * Siapkan parameter lokasi
     */
    if (isset($params['m_lokasi_id'])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if (!empty($lokasiId)) {
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    /**
     * Proses laporan
     */
    if (isset($params['m_akun_id']) && isset($params['m_lokasi_id'])) {
        /*
         * ambil supplier
         */
//        $supplier = $sql->select("*")
//                ->from("acc_m_kontak")
//                ->where("is_deleted", "=", 0)
//                ->where("type", "=", "customer")
//                ->findAll();
        $data['totalSaldoAwal'] = 0;
        $data['totalDebit'] = 0;
        $data['totalKredit'] = 0;
        $data['totalSaldoAkhir'] = 0;
        $arr = [];
//        foreach($supplier as $key => $val){
//            $arr[$key] = (array) $val;
        /*
         * ambil saldo awal piutang ke customer
         */
        $sql->select('acc_m_kontak.*, acc_trans_detail.m_kontak_id, acc_trans_detail.debit, acc_trans_detail.kredit')
                ->from('acc_trans_detail')
                ->join("JOIN", "acc_m_kontak", "acc_m_kontak.id = acc_trans_detail.m_kontak_id");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
        }
        $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
//                ->andWhere('acc_trans_detail.m_kontak_id', '=', $val->id)
                ->andWhere('date(acc_trans_detail.tanggal)', '<', $tanggal_start);
        $getsaldoawal = $sql->findAll();
        $arrkontak = [];
        $arrawal = [];
        foreach ($getsaldoawal as $key => $val) {
            if (isset($arrawal[$val->m_kontak_id])) {
                $arrawal[$val->m_kontak_id]['debit'] += $val->debit;
                $arrawal[$val->m_kontak_id]['kredit'] += $val->kredit;
            } else {
                $arrawal[$val->m_kontak_id] = (array) $val;
                if (!in_array($val->m_kontak_id, $arrkontak)) {
                    $arrkontak[] = $val->m_kontak_id;
                }
            }
        }
        /*
         * ambil saldo hutang di rentan tanggal dari customer
         */
        $sql->select('acc_m_kontak.*, acc_trans_detail.m_kontak_id, acc_trans_detail.debit, acc_trans_detail.kredit')
                ->from('acc_trans_detail')
                ->join("JOIN", "acc_m_kontak", "acc_m_kontak.id = acc_trans_detail.m_kontak_id");
        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
        }
        $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
//                ->andWhere('acc_trans_detail.m_kontak_id', '=', $val->id)
                ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
                ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
        $getsaldopiutang = $sql->findAll();
        $arrperiode = [];
        foreach ($getsaldopiutang as $key => $val) {
            if (isset($arrperiode[$val->m_kontak_id])) {
                $arrperiode[$val->m_kontak_id]['debit'] += $val->debit;
                $arrperiode[$val->m_kontak_id]['kredit'] += $val->kredit;
            } else {
                $arrperiode[$val->m_kontak_id] = (array) $val;
                if (!in_array($val->m_kontak_id, $arrkontak)) {
                    $arrkontak[] = $val->m_kontak_id;
                }
            }
        }
        /*
         * gabungkan keduanya
         */
        $data['totalSaldoAwal'] = 0;
        $data['totalDebit'] = 0;
        $data['totalKredit'] = 0;
        $data['totalSaldoAkhir'] = 0;
        $arr = [];
        foreach ($arrkontak as $key => $val) {
            if (isset($arrawal[$val])) {
                $arr[$val]['saldoAwal'] = $arrawal[$val]['debit'] - $arrawal[$val]['kredit'];
                $arr[$val]['kode'] = $arrawal[$val]['kode'];
                $arr[$val]['nama'] = $arrawal[$val]['nama'];
            } else {
                $arr[$val]['saldoAwal'] = 0;
            }
            if (isset($arrperiode[$val])) {
                $arr[$val]['debit'] = $arrperiode[$val]['debit'];
                $arr[$val]['kredit'] = $arrperiode[$val]['kredit'];
                $arr[$val]['kode'] = $arrperiode[$val]['kode'];
                $arr[$val]['nama'] = $arrperiode[$val]['nama'];
            } else {
                $arr[$val]['debit'] = 0;
                $arr[$val]['kredit'] = 0;
            }
            $arr[$val]['saldoAkhir'] = $arr[$val]['saldoAwal'] + $arr[$val]['debit'] - $arr[$val]['kredit'];
            $data['totalSaldoAwal'] += $arr[$val]["saldoAwal"];
            $data['totalDebit'] += $arr[$val]["debit"];
            $data['totalKredit'] += $arr[$val]["kredit"];
            $data['totalSaldoAkhir'] += $arr[$val]["saldoAkhir"];
        }
//        }
        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/rekapPiutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-rekap-piutang.xls");
            echo $content;
        } elseif (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/rekapPiutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl() . '/assets/css/style.css',
            ]);
            echo $content;
            echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
        } else {
            return successResponse($response, ["data" => $data, "detail" => $arr]);
        }
    } else {
        return unprocessResponse($response, ["Silahkan pilih lokasi dan akun terlebih dahulu"]);
    }
});
