<?php

class Menu extends dbConnect {
	
	function __construct() {
		self::connect();
	}
	
	function getChildren($parentID,$roleID) {
		$retval ="";
		if ($roleID != 1) {
			$rs = $this->get_data("select class,a.Name,b.Stubs from Admin.Services a, Admin.Category b where a.ParentID=b.ID and a.ParentID=? and Access like ? order by OrderID",[$parentID,'%:'.$roleID.':%']);
		}else {
			$rs = $this->get_data("select class,a.Name,b.Stubs from Admin.Services a, Admin.Category b where a.ParentID=b.ID and a.ParentID=? order by OrderID",[$parentID]);
		}
		if (is_array($rs)) {
			$retval ='<ul class="sub-menu collapse" id="'.$rs[0]['Stubs'].'" data-parent="#side-nav">';
			foreach($rs as $row) {
				$retval.='<li><a href="#" title="'.$row['class'].'">'.$row['Name'].'</a></li>';
			}
			$retval.='</ul>';
		}
		return $retval;
	}
}
?>