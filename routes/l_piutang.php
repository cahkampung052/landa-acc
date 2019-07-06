<?php
$app->get('/acc/l_piutang/laporan', function ($request, $response) {
    $params = $request->getParams();
    $sql = $this->db;
    
//    print_r($params);die();
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
    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' s/d ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = (isset($params['nama_lokasi']) && !empty($params['nama_lokasi'])) ? $params['nama_lokasi'] : "Semua";
    
    /*
     * Siapkan parameter lokasi
     */
    if(isset($params['m_lokasi_id'])){
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if(!empty($lokasiId)){
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */
        else{
            $lokasiId = $params['m_lokasi_id'];
        }
    }
    
    
    /**
     * Proses laporan
     */
    if (isset($params['m_akun_id']) && isset($params['m_lokasi_id'])) {
        
        /*
         * ambil saldo awal hutang
         */
        $sql->select('sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit')
            ->from('acc_trans_detail');
            if(isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])){
                $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
            }
        $sql->andWhere('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
            ->andWhere('acc_trans_detail.m_customer_id', '=', $params['m_customer_id'])
            ->andWhere('date(acc_trans_detail.tanggal)', '<', $tanggal_start);

        $getsaldohutang = $sql->find();
        $data["saldoAwal"] = $getsaldohutang->debit - $getsaldohutang->kredit;
        
        /*
         * ambil data transdetail hutang
         */
        $sql->select('acc_trans_detail.*')
            ->from('acc_trans_detail');
            if(isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])){
                $sql->customWhere("acc_trans_detail.m_lokasi_id IN ($lokasiId)");
            }
        $sql->where('acc_trans_detail.m_akun_id', '=', $params['m_akun_id'])
            ->andWhere('acc_trans_detail.m_customer_id', '=', $params['m_customer_id'])
            ->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)
            ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
        $listTransDetail = $sql->findAll();
//        print_r($listTransDetail);die();

        $arr = [];
        
        $data["debitAkhir"] = 0;
        $data["kreditAkhir"] = 0;
        $saldoSekarang = $data["saldoAwal"];
        foreach($listTransDetail as $key => $val){
            $val->debit = intval($val->debit);
            $val->kredit = intval($val->kredit);
            $arr[$key] = (array) $val;
            $arr[$key]['saldo_sekarang'] = $saldoSekarang + intval($val->debit) - intval($val->kredit);
            
            $data["debitAkhir"] += intval($val->debit);
            $data["kreditAkhir"] += intval($val->kredit);
            
            $saldoSekarang += $arr[$key]['saldo_sekarang'];
            
        }
        
        $data["saldoAkhir"] = $data["debitAkhir"] - $data["kreditAkhir"];
//        print_r($data);die();
        
        if (isset($params['export']) && $params['export'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/piutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
            ]);
            header("Content-type: application/vnd.ms-excel");
            header("Content-Disposition: attachment;Filename=laporan-buku-besar.xls");
            echo $content;
        } else if (isset($params['print']) && $params['print'] == 1) {
            $view = twigViewPath();
            $content = $view->fetch('laporan/piutang.html', [
                "data" => $data,
                "detail" => $arr,
                "css" => modulUrl().'/assets/css/style.css',
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
