<?php

class Role extends dbConnect {
    
    function getRoles() {
        $rs = $this->get_data("Select * from Admin.Roles where RoleID != 1 ");
        return $rs;
    }

    function getServices($roleID) {
        $roleID = '%:'.$roleID.':%';
        $rs = $this->get_data("SELECT group_concat(ParentID) AS `ModuleName` FROM Admin.Services a, Admin.Category b WHERE a.ParentID=b.ID and Access like ? order by b.Name", [$roleID]);
        return $rs[0]['ModuleName'];
    }

    function createRole($rolename,$services) {
        $this->insert_data("insert into Roles(Role, Services) values (?,?)", [$rolename, $services]);
    }

    function getRightsByRoleID($roleID) {
        $rs = $this->get_data("SELECT a.*, b.Name AS `ModuleName` FROM Admin.Services a, Admin.Category b WHERE a.ParentID=b.ID and Access like ? order by b.Name",  ['%:'.$roleID.':%']);
        return $rs;
    }

    function getOtherRights($roleID) {
        $rs = $this->get_data("SELECT a.*, b.Name AS `ModuleName` FROM Admin.Services a, Admin.Category b WHERE a.ParentID=b.ID and Access not like :search  order by b.Name", ["search"=>"%:$roleID:%"]);
        return $rs;
    }

    function grantAccessRight($roleID, $serviceID) {
        $this->insert_data("Update Admin.Services set Access= concat(ifnull(Access,''), ?, ':') where ID=?", [$roleID, $serviceID]);
        return true;
    }

    function revokeAccessRight($roleID, $serviceID) {
        $this->insert_data("Update Admin.Services set Access=replace(Access,concat(?,':'),'') where ID=?",  [$roleID, $serviceID]);
        
        return true;
    } 


}

?>