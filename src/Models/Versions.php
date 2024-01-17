<?php
/**
 * Description of Versions
 * @author zeroc0de <98693638+zeroc0de2022@users.noreply.github.com>
 * Date 2021.08.06
 */

namespace Api\Models;


use Api\Common\Database;
use Api\Common\Helper;

class Versions {
    private array $return = [];
    
    public function all_versions($dots): void
    {
        $stmt = Database::getConnection()->query("SELECT `versions` FROM `items`");
        if(!$stmt->RowCount()){
            $this->return['error'] = true;
            $this->return['message'] = "Версии в системе не найдены";
        }
        else {
            $all_versions = [];
            while($row = $stmt->fetchAll()){
                $versions = json_decode($row['versions']);
                $all_versions = array_values(array_unique(array_merge($all_versions, $versions)));
            }
            sort($all_versions);
            $this->select_versions($all_versions, $dots);
            Helper::printPre(json_encode($this->return, JSON_UNESCAPED_UNICODE), false); 
        }        
    }
    
    
    private function select_versions($all_versions, $dots = 1): void
    {
        for($i = 0; $i < count($all_versions); $i++ ){
            if($dots == 0){
                $this->return[] = $all_versions[$i];
            }
            else {
                if(substr_count($all_versions[$i], '.') == $dots){
                    $this->return[] = $all_versions[$i];
                }
            }
        }
    }
    
    
    
    
}
