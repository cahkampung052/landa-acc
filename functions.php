<?php
/**
 * Buat nested tree
 */
function buildTree($elements, $parentId = 0)
{
    $branch = array();

    foreach ($elements as $element) {
        if ($element->parent_id == $parentId) {
            $children = buildTree($elements, $element->id);
            if ($children) {
                $element->children = $children;
            }
            $branch[$element->id] = $element;
        }
    }

    return $branch;
}
/**
 * ubah id child jadi numerical array
 */
function buildFlatTree($tree, $ids = [])
{
    $colName = 'id';
    $childColName = 'children';
    foreach ($tree as $element) {
        if (!isset($element->$colName)) {
            continue;
        }
        $ids[] = $element->$colName;

        if (isset($element->$childColName) && count($element->$childColName) > 0) {
            $ids = buildFlatTree($element->$childColName, $ids);
        }
    }
    return $ids;
}
/**
 * Ambil semua id child
 */
function getChildId($tabelName, $parentId)
{
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $db->select("*")->from($tabelName)->where("is_deleted", "=", 0);
    $data = $db->findAll();
    $tree = buildTree($data, $parentId);
    $child = buildFlatTree($tree);

    return $child;
}

function getLabaRugi($tanggal_start, $lokasi=null) {
    
    $sql = new Cahkampung\Landadb(config('DB')['db']);

    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;

    $arr = [];

    $arr_klasifikasi = [
        "PEMASUKAN" => "'Pendapatan', 'Pendapatan Usaha', 'Pendapatan Non Usaha'",
        "HPP" => "'Hpp'",
        "PENGELUARAN" => "'Biaya Operasional', 'Biaya Non Operasional'"
    ];
//        print_r($arr_klasifikasi);die();
//        $index = 0;

    foreach ($arr_klasifikasi as $index => $akun) {

        $arr[$index]['nama'] = $index;
        $arr[$index]['total'] = 0;

        $getakun = $sql->select("*")
                ->from("acc_m_akun")
                ->customWhere("tipe IN($akun)")
                ->where("is_tipe", "=", 0)
                ->where("is_deleted", "=", 0)
                ->orderBy("kode")
                ->findAll();


        foreach ($getakun as $key => $val) {

            $sql->select("SUM(debit) as debit, SUM(kredit) as kredit")
                    ->from("acc_trans_detail")
                    ->where('acc_trans_detail.m_akun_id', '=', $val->id)
                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_start);
//                    ->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
            if (isset($lokasi) && !empty($lokasi)) {
                $sql->andWhere('acc_trans_detail.m_lokasi_id', '=', $lokasi);
            }
            $gettransdetail = $sql->find();
            if (intval($gettransdetail->debit) - intval($gettransdetail->kredit) > 0) {
                $arr[$index]['detail'][$key]['kode'] = $val->kode;
                $arr[$index]['detail'][$key]['nama'] = $val->nama;
                $arr[$index]['detail'][$key]['nominal'] = intval($gettransdetail->debit) - intval($gettransdetail->kredit);
                $arr[$index]['total'] += $arr[$index]['detail'][$key]['nominal'];
            }
        }
    }
    
    return $arr;
}

