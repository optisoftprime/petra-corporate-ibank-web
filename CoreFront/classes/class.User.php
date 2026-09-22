<?php

class User extends dbConnect {

    function getUserDetails($userid) {
        $rs = $this->get_data("Select UserID, Firstname, TxnLimit, SingleTxnLimit,(Select ifnull(sum(Amount),0) from Transfers where date(DateApproved)=date(now()) and Status='Successful' and if(a.Role='Authorizer',Authorizer=a.UserID, Initiator=a.UserID)) as UsedLimit, Mobile, Email, Role, Status,CustomerID, AccountName, OfficeAddress, OfficePhone from Credentials a where UserID=?",[$userid]);
        if(is_array($rs)) {
            return $rs[0];
        }else {
            return false;
        }
    }

    function getUserDetailsByRowID($id) {
        $rs = $this->get_data("Select UserID, Firstname, TxnLimit, SingleTxnLimit, Mobile, Email, Role, Status,CustomerID, AccountName, OfficeAddress, OfficePhone from Credentials where ID=?",[$id]);
        if(is_array($rs)) {
            return $rs[0];
        }else {
            return false;
        }
    }

    function getAllUsersByCustomerID($cod_cust) {
        return $this->get_data("Select  ID, Firstname, Lastname, Mobile, Email, Role, SingleTxnLimit, TxnLimit, UserID, Status from dbPetra.Credentials where CustomerID=? ",[$cod_cust]);
    }


    // Ownership check ($cod_cust): without it, any authenticated session could lock ANY
    // other customer's login system-wide, not just one under their own business, by passing
    // an arbitrary UserID. Confirmed via a SELECT first -- insert_data() can't report
    // affected-row counts -- so the delete below is never reached for a login outside the
    // caller's own CustomerID.
    function lockUser($userId, $cod_cust) {
        $rs = $this->get_data("Select ID from dbPetra.Credentials where UserID=? and CustomerID=?",[$userId, $cod_cust]);
        if (!is_array($rs)) {
            return false;
        }
        $this->insert_data("Update dbPetra.Credentials set Status='Locked' where UserID=?",[$userId]);
        $this->insert_data("delete from dbPetra.AccessStore where UserID=?",[$userId]);
        return true;
    }

    function createUser($data) {
        $rs = $this->get_data("Select * from Credentials where lower(Email)=lower(?) and Status !='Deleted' ",[$data['userID']]);
        if(!is_array($rs)) {
            $this->insert_data("Insert into Credentials (UserID, Email, Mobile, CustomerID, TxnLimit, SingleTxnLimit, Role, Firstname, Lastname, Status) values(?,?,?,?,?,?,?,?,?,'New')",[$data['userID'],$data['email'],$data['mobile'],$data['customerID'],$data['txnLimit'],$data['singleTxnLimit'],$data['role'],$data['Firstname'],$data['Lastname']]);
            return true;
        }else {
            return false;
        }
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    function forgot_password($email) {
        $password = $this->generateRandomString();
        $user_details = $this->get_data("Select * from Credentials where UserID=? and Status !='Locked'",[$email]);
        
        if(is_array($user_details)) {
            $rs = $this->get_data("Select Content from Contents where Type='RESET'");
            $mail_content = str_replace('[Password]',$password,$rs[0]['Content']);
            $mail_content = str_replace('[FirstName]',$user_details[0]['Firstname'],$mail_content);

            $mailer = new Mailer();

            $mailer->AddAddress($user_details[0]['Email']);
            $mailer->set('Subject','Your Temporary Password');  
            $mailer->msgHTML($mail_content);
            $mailer->Send();
            
			$this->insert_data("Update Credentials set Status='ChangePassword', Password=? where UserID=?",[password_hash($password,PASSWORD_BCRYPT,array("cost"=>10)),$email]);
            return true;
        }else {
            return false;
        }
    }

    function updateStatus($status, $id) {
        $this->insert_data("Update Credentials set Status=?, Trials=0 where ID=?",[$status,$id]);
        return true;
    }

    function resetUser($id) {
        $rs = $this->get_data("Select * from Credentials where ID=? and Status !='New'",[$id]);
        if(is_array($rs)) {
            $this->insert_data("Update Credentials set Status='PendingReset' where ID=?",[$id]);
            return true;
        }else {
            return false;
        }
    }

    function approveResetUser($id) {
        $password = $this->generateRandomString();
        $user_details = $this->get_data("Select * from Credentials where ID=? and Status='PendingReset'",[$id]);
        
        if(is_array($user_details)) {
            $rs = $this->get_data("Select Content from Contents where Type='RESET'");
            $mail_content = str_replace('[Password]',$password,$rs[0]['Content']);
            $mail_content = str_replace('[FirstName]',$user_details[0]['Firstname'],$mail_content);
            
            $mailer = new Mailer();
            $mailer->AddAddress($user_details[0]['Email']);
            $mailer->set('Subject','Petra Online Banking Password Reset');
            $mailer->msgHTML($mail_content);
            $mailer->Send();

            $this->insert_data("Update Credentials set Status='Active', Password=? where ID=?",[password_hash($password,PASSWORD_BCRYPT,array("cost"=>10)),$id]);
            return true;
        }else {
            return false;
        }
    }

    function approveUser($id) {
        $password = $this->generateRandomString();
        $user_details = $this->get_data("Select * from Credentials where ID=? and Status='New'",[$id]);
        if(is_array($user_details)) {
            $rs = $this->get_data("Select Content from Contents where Type='WELCOME'");
            $mail_content = str_replace('[Password]',$password,$rs[0]['Content']);
            $mail_content = str_replace('[FirstName]',$user_details[0]['Firstname'],$mail_content);
            $mail_content = str_replace('[Username]',$user_details[0]['UserID'],$mail_content);
            $mailer = new Mailer();
            $mailer->AddAddress($user_details[0]['Email']);
            $mailer->set('Subject','Welcome to Petra ');  
            $mailer->msgHTML($mail_content);
            $mailer->Send();

            $this->insert_data("Update Credentials set Password=?, Status='ChangePassword' where ID=?",[password_hash($password,PASSWORD_BCRYPT),$id]);
            return true;
        }else {
            return false;
        }
    }

    function changePassword($username, $currentPassword, $newPassword) {
        $rs = $this->get_data("Select * from Credentials where UserID=?",[$username]);
        if(is_array($rs)) {
            if(password_verify($currentPassword, $rs[0]['Password'])) {
               $this->insert_data("Update Credentials set Password=?, Status='Active' where UserID=?",[password_hash($newPassword,PASSWORD_BCRYPT),$username]);
               return true;
            }else {
                return false;
            }
        }else {
            return false;
        }
    }

    function createOTP($username) {
        $otp_code = random_int(111111, 999999);
       
        $this->insert_data("insert into OTP(OTP, UserID,Type, Age) values(?,?,'Authorization', date_add(now(), interval 240 second))",[$otp_code,$username]);
        $details = $this->getUserDetails($username);

        $rs = $this->get_data("Select Content from Contents where Type='OTPCODE'");
        $mail_content = str_replace('[OTP_CODE]',$otp_code,$rs[0]['Content']);
        $mail_content = str_replace('[Customer Name]',$details['Firstname'],$mail_content);
        $mailer = new Mailer();

        $mailer->AddAddress($details['Email']);
        $mailer->set('Subject','Your OTP Code');  
		$mailer->msgHTML($mail_content);
        $mailer->Send();

        //send SMS
        $rs = $this->get_data("Select * from Credentials where UserID=?",[$username]);
        $bankone = new BankOne();
        $bankone->sendSMS($rs[0]['Mobile'], "Your OTP is ". $otp_code .". Please note that this OTP will expire in 4 minutes Petra Microfinance Bank");

        return true;
    }

    function login($username, $password) {
        $rs = $this->get_data("Select * from Credentials where UserID=?",[$username]); 
        if(is_array($rs)) {
              switch($rs[0]['Status']) {
                case "Active": case "ChangePassword": {
                    if(password_verify($password, $rs[0]['Password'])) { 
                        $details =  $this->getUserDetails($username);
                        $otp = random_int(111111, 999999);
                        $this->insert_data("insert into OTP(OTP, UserID) values(?,?)",[$otp, $rs[0]['UserID']]);

                        $rs_ = $this->get_data("Select Content from Contents where Type='OTPCODE'");
                        $mail_content = str_replace('[OTP_CODE]',$otp,$rs_[0]['Content']);
                        $mail_content = str_replace('[Customer Name]',$details['Firstname'],$mail_content);
                        $mailer = new Mailer();
                        $mailer->AddAddress($details['Email']);
                        $mailer->set('Subject','Your OTP Code');  
                        $mailer->msgHTML($mail_content);
                        $mailer->Send();

                        //send SMS
                        $bankone = new BankOne();
                        $bankone->sendSMS($rs[0]['Mobile'], "Your OTP to login to Petra Internet Banking is ". $otp. ". Please note that this OTP will expire in 90 seconds. Petra Microfinance Bank");
                        
                        return array("user_id"=>$rs[0]['CustomerID'], "success"=> true, "status"=>$rs[0]['Status']);
                    }else {
                        $rs = $this->insert_data("Update Credentials set Trials=Trials + 1 where UserID=?",[$username]);
                         return array("success"=> false);
                    }
                    break;
                }
                case "Locked": {
                    return array("success"=> false, "status"=> "locked");
                    break;
                }
                default: {
                    return array("success"=> false, "status"=> "locked");
                    break;
                }
            }
        }

    }

    function getTransactionPIN($username) {
        $rs = $this->get_data("Select * from OTP where UserID=? and Age > now() and Type='Authorization' and Status='New' order by ID desc limit 1",[$username]);
        if(is_array($rs)) {
            return $rs[0]['OTP'];
        }
    }

    function expireTxnPIN($username, $pin) {
        $rs = $this->get_data("Update OTP set Status='Used', DateUsed=now() where UserID=? and Type='Authorization' and Status='New' and OTP=? ",[$username, $pin]);
        if(is_array($rs)) {
            return $rs[0]['OTP'];
        }
    }


    private $redis_conn;

    /**
     * Lazily open (and memoise) a Redis connection. Returns false when Redis
     * is unreachable so callers can fail closed on session lookups (no
     * session found = 401, same as any other invalid/expired session) rather
     * than crash the request.
     */
    private function redis() {
        if ($this->redis_conn !== null) {
            return $this->redis_conn;
        }
        try {
            $r = new Redis();
            $r->connect(getenv('REDIS_HOST') ?: '127.0.0.1', (int)(getenv('REDIS_PORT') ?: 6379), 2.0);
            $pw = getenv('REDIS_PASSWORD');
            if (!empty($pw)) {
                $r->auth($pw);
            }
            $this->redis_conn = $r;
        } catch (Throwable $e) {
            error_log("User::redis connect failed: " . $e->getMessage());
            $this->redis_conn = false;
        }
        return $this->redis_conn;
    }

    /** Session lifetime in seconds — set via the SESSION_TTL env var (default 3600 = 1h). */
    private function sessionTtl() {
        $ttl = (int)getenv('SESSION_TTL');
        return $ttl > 0 ? $ttl : 3600;
    }

    /**
     * Open a session in Redis: stores the session record (UserID + per-login
     * AES key/IV) under session:<token>, and a user:active:<UserID> pointer
     * so a new login can evict the user's previous session (single session
     * per user). Returns false when Redis is unreachable.
     */
    function startSession($userId, $token, $cryptoKey, $cryptoIv) {
        $r = $this->redis();
        if (!$r) {
            return false;
        }
        try {
            $ttl = $this->sessionTtl();
            $prev = $r->get('user:active:' . $userId);
            if ($prev !== false && $prev !== null) {
                $r->del('session:' . $prev);
            }
            $r->setex('session:' . $token, $ttl, json_encode([
                'UserID'    => $userId,
                'CryptoKey' => $cryptoKey,
                'CryptoIV'  => $cryptoIv
            ]));
            $r->setex('user:active:' . $userId, $ttl, $token);
            return true;
        } catch (Throwable $e) {
            error_log("User::startSession error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate-and-fetch a session in a single Redis GET. Returns the
     * session record (UserID, CryptoKey, CryptoIV) or null. Refreshes the
     * TTL on use.
     */
    function getSession($token) {
        $r = $this->redis();
        if (!$r) {
            return null;
        }
        try {
            $raw = $r->get('session:' . $token);
            if ($raw === false || $raw === null) {
                return null;
            }
            $rec = json_decode($raw, true);
            if (!is_array($rec) || !isset($rec['UserID'], $rec['CryptoKey'], $rec['CryptoIV'])) {
                return null;
            }
            $ttl = $this->sessionTtl();
            $r->expire('session:' . $token, $ttl);
            $r->expire('user:active:' . $rec['UserID'], $ttl);
            return $rec;
        } catch (Throwable $e) {
            error_log("User::getSession error: " . $e->getMessage());
            return null;
        }
    }

    function validateOTP($userId, $otp_code) {
        $rs = $this->get_data("Select * from OTP a, Credentials b  where a.UserID=b.UserID and a.UserID=? and date_add(Age, interval 90 second) > now() order by a.ID desc limit 1",[$userId]);
        if(is_array($rs)) {
            if(trim($otp_code) == $rs[0]['OTP']) {
                $this->insert_data("Update Credentials set DateLastLogin=now() where UserID=?",[$userId]);
                // High-entropy random value: the token's security comes from its own
                // randomness, not from being encrypted.
                $session_token = bin2hex(random_bytes(32));
                $crypto_key = bin2hex(random_bytes(8)); // 16-char key for AES-128-CBC
                $crypto_iv  = bin2hex(random_bytes(8)); // 16-char IV
                // AccessStore is an append-only audit trail — one row per login, never
                // read on the hot path. The session itself (record + per-login key/IV)
                // lives in Redis via startSession(); if Redis is down the session can't
                // be opened, so the keys are omitted from the response.
                $this->insert_data("insert into AccessStore(UserID, Signature, Age) values(?, ?,date_add(now(), interval 1 hour))", [$rs[0]['UserID'], $session_token]);
                $payload = array("user_id"=>$rs[0]['UserID'], "session_token"=>$session_token, "status"=>$rs[0]['Status']);
                if ($this->startSession($rs[0]['UserID'], $session_token, $crypto_key, $crypto_iv)) {
                    $payload['crypto_key'] = $crypto_key;
                    $payload['crypto_iv'] = $crypto_iv;
                }
                return json_encode($payload);
            }else {
                return false;
            }
        }
    }
}

?>