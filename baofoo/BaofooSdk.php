<?php

require_once "../baofoo/TransContent.php";
require_once "../baofoo/TransDataUtils.php";

    if ( !function_exists( 'hex2bin' ) ) {
        function hex2bin( $str ) {
            $sbin = "";
            $len = strlen( $str );
            for ( $i = 0; $i < $len; $i += 2 ) {
                $sbin .= pack( "H*", substr( $str, $i, 2 ) );
            }

            return $sbin;
        }
    }

/*
 * 宝付代付款SDK
 * 供宝付商户快速集成使用
 * 接口采用标准接口方法实现，数组作为传输数据类型
 * 接口仅供参考，商户可自行根据实际需求修改此SDK
 */
 define("BAOFOO_ENCRYPT_LEN", 32);

 class BaofooSdk{
    
     private $member_id;
     private $terminal_id;
     private $data_type;
     private $private_key;
	 private $public_key;
    
    /**
     * 
     * @Param  $member_id 会员号
     * @Param  $terminal_id 终端号
     * @Param  $data_type 数据类型(json|xml)
     * @Param  $private_key_path 商户证书路径,要绝对路径（pfx）
	 * @Param  $public_key_path 宝付公钥证书路径,要绝对路径（cer）
     * @Param  $private_key_password 证书密码
     */
     public function __construct($member_id, $terminal_id, $data_type, $private_key_path,$public_key_path,$private_key_password,$debug= false){
        

         $this -> member_id = $member_id;
         $this -> terminal_id = $terminal_id;
         $this -> data_type = $data_type;
        
         // 初始化商户私钥
         $pkcs12 = file_get_contents($private_key_path);
         $private_key = array();
         openssl_pkcs12_read($pkcs12, $private_key, $private_key_password);

         $this -> private_key = $private_key["pkey"];
		 

		 $keyFile = file_get_contents($public_key_path);
		 $this->public_key = openssl_get_publickey($keyFile);

         if($debug){
             echo '会员号：', $member_id, "终端号：", $terminal_id, "\n";
             echo "公钥路径：", $public_key_path, "\n";
             echo "私钥是否可用:", empty($private_key) == true ? '不可用':'可用', "\n";
             echo "宝付公钥是否可用:", empty($this -> public_key) == true ? '不可用':'可用', "\n\n".PHP_EOL;
         }


     }


    // __get()方法用来获取私有属性	
    public function _get($property_name)
    {
        echo "获取属性：",$property_name."，值：",$this->$property_name,"\n";
        if (isset($this->$property_name)) {  //判断一下

            return $this->$property_name;
        } else {
            echo '没有此属性！'.$property_name;
        }

    }
    
     // 私钥加密
    private function encryptedByPrivateKey($data_content){

		$data_content = base64_encode($data_content);
         $encrypted = "";
         $totalLen = strlen($data_content);
         $encryptPos = 0;

         while ($encryptPos < $totalLen){
             openssl_private_encrypt(substr($data_content, $encryptPos, BAOFOO_ENCRYPT_LEN), $encryptData, $this -> private_key);
             $encrypted .= bin2hex($encryptData);
             $encryptPos += BAOFOO_ENCRYPT_LEN;
             }

         return $encrypted;

    }
		
	// 公钥解密
    private  function decryptByPublicKey($encrypted){
          $decrypt = "";
          $decryptPos = 0;
          $totalLen = strlen($encrypted);

          while ($decryptPos < $totalLen) {
              openssl_public_decrypt(hex2bin(substr($encrypted, $decryptPos, BAOFOO_ENCRYPT_LEN * 8)), $decryptData, $this->public_key);
              $decrypt .= $decryptData;
              $decryptPos += BAOFOO_ENCRYPT_LEN * 8;
          }

		  //openssl_public_decrypt($encrypted, $decryptData, $this->public_key);
		  $decrypt = base64_decode($decrypt);

          return $decrypt;

     }


	 function post($encrypted,$request_url){

//		echo "发送地址：",$request_url,"<br>";
		$postData = array(
			 "member_id" => $this->member_id,
			 "terminal_id" => $this->terminal_id,
			 "data_type" => $this->data_type,
			 "data_content" => $encrypted,
			 "version" => "4.0.0"
		);

		$context = array(
			'http' => array(
				 'method' => 'POST',
				 'header' => 'Content-type: application/x-www-form-urlencoded',
				 'content' => http_build_query($postData),
                 'timeout' => 3 * 60
            )
		);
		# var_dump($context);
		 $streamPostData = stream_context_create($context);

		 $httpResult = file_get_contents($request_url, false, $streamPostData);
		 return $httpResult;
     }

     public function _array2Json($data){

         $tmp= [];

         array_push($tmp,array("trans_reqData"=>$data));

         $trans_content0 = new TransContent();
         $trans_content0 -> __set("trans_reqDatas", $tmp);

         $trans_content = new TransContent();
         $trans_content -> __set("trans_content", $trans_content0 -> __getTransContent());

         $data_content = TransDataUtils :: __array2Json($trans_content -> __getTransContent());

         $data_content = str_replace("\\\"",'"',$data_content);

         return $data_content;
     }

     public function _splitApiArray2Json($data){

         $trans_totalMoney = 0;
         foreach($data as $row){
             $trans_totalMoney += $row['trans_money'];
         }

         $head = [
                     'trans_count'=> count($data),
                     'trans_totalMoney'=> $trans_totalMoney,
         ];

         $trans_reqData = [];

         array_push($trans_reqData,array("trans_reqData"=>$data));

         $trans_content0 = new TransContent();
         $trans_content0 -> __set("trans_head", $head);
         $trans_content0 -> __set("trans_reqDatas", $trans_reqData);

         $trans_content = new TransContent();
         $trans_content -> __set("trans_content", $trans_content0 -> __getTransContent());

         $data_content = TransDataUtils :: __array2Json($trans_content->__getTransContent());
         $data_content = str_replace("\\\"",'"',$data_content);

         return $data_content;
     }

     public function apiResponse($code,$message){

        $response  = ['trans_content' => ['trans_head'=> [
                                 'return_code'=> $code,
                                 'return_msg'=> $message,
                                ]]
                   ];
         return json_encode($response) ;
     }


     /*
      * 代付交易接口,该接口的主要功能为代付交易,代付交易一次处理的请求条数（trans_reqData）有限制，
      * 不超过5个，超过5个：交易请求记录条数超过上限!
      *
      * */
     public function agentPayApi($data,$request_url = 'http://paytest.baofoo.com/baofoo-fopay/pay/BF0040001.do'){

         if(empty($data) || count($data) >5){
           return  $this->apiResponse('0004','交易请求记录条数超过上限!');
         }

         $data_content = $this->_array2Json($data);
         $encrypted = $this -> encryptedByPrivateKey($data_content);

         $httpResult = $this -> post($encrypted, $request_url);

         if(count(explode("trans_content",$httpResult)) > 1 ){
             $decrypt = $httpResult;
         }else{
             $decrypt = $this -> decryptByPublicKey($httpResult);
         }

         return  $decrypt;
     }

     /*
      * 代付交易状态查证,该接口的主要功能为查询代付交易状态,
      *
      * */
     public function agentPayStatusQueryApi($data,$request_url = 'http://paytest.baofoo.com/baofoo-fopay/pay/BF0040002.do'){

         if(empty($data) || count($data) > 5  ){

           return  $this->apiResponse('0004','交易请求记录条数超过上限!');
         }

         $data_content = $this->_array2Json($data);
         $encrypted = $this -> encryptedByPrivateKey($data_content);

         $httpResult = $this -> post($encrypted, $request_url);

         if(count(explode("trans_content",$httpResult))>1){
             $decrypt = $httpResult;
         }else{
             $decrypt = $this -> decryptByPublicKey($httpResult);
         }

         return $decrypt;
     }


     /*
     * 代付交易状态查证,该接口的主要功能为查询代付交易状态,
     *  代付交易退款查证接口,该接口的主要功能为查询代付交易退款订单,代付交易退款查证一次处理的请求条数（trans_reqData）有限制，不超过1个,
     * 且时间限制为同为一天
     * */
     public function agentPayRefundQueryApi($data,$request_url = 'http://paytest.baofoo.com/baofoo-fopay/pay/BF0040003.do'){

         if(empty($data) || count($data)>1 ){
             return  $this->apiResponse('0004','交易请求记录条数超过上限!');
         }

         $data_content = $this->_array2Json($data);
         $encrypted = $this -> encryptedByPrivateKey($data_content);

         $httpResult = $this -> post($encrypted, $request_url);

         if(count(explode("trans_content",$httpResult)) >1 ){
             $decrypt = $httpResult;
         }else{
             //业务逻辑信息处理
             $decrypt = $this -> decryptByPublicKey($httpResult);
         }

         return $decrypt;
     }


     /*
      * 该接口的主要功能为代付交易拆封接口。原接口BF0040001不能对私进行拆分，不能满足客户需求，故增加本接口.
      * 代付交易一次处理的请求条数（trans_reqData）有限制，不超过5个，超过5个
      *
      *  */
     public function agentPaySplitApi($data,$request_url = 'http://paytest.baofoo.com/baofoo-fopay/pay/BF0040003.do'){

         if(empty($data) || count($data)> 5 ){
             return  $this->apiResponse('0004','交易请求记录条数超过上限!');
         }

         $data_content = $this->_splitApiArray2Json($data);
         $encrypted = $this -> encryptedByPrivateKey($data_content);

         $httpResult = $this -> post($encrypted, $request_url);

         if(count(explode("trans_content",$httpResult)) >1 ){
             $decrypt = $httpResult;
         }else{
             $decrypt = $this -> decryptByPublicKey($httpResult);
         }

         return $decrypt;
     }



 }