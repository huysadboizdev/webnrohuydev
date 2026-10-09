<?php
/** Exact identity shared with Java and migration; never changes catalog claims. */
function giftcode_canonical($raw): string {
 if(!is_string($raw))throw new InvalidArgumentException('Missing giftcode');
 $code=strtoupper(trim($raw," \t\n\r\x0B\x0C"));
 if(!preg_match('/^[\x21-\x7E]{1,100}$/D',$code))throw new InvalidArgumentException('Giftcode must contain 1-100 printable ASCII characters without spaces');
 return $code;
}
function giftcode_integer($raw,int $min=0,int $max=2147483647): int {
 if(!is_int($raw)&&!is_string($raw)||!preg_match('/^-?[0-9]+$/D',(string)$raw))throw new InvalidArgumentException('Invalid giftcode integer');
 if(strlen(ltrim((string)$raw,'-0'))>10)throw new InvalidArgumentException('Giftcode integer overflow');
 $value=(int)$raw;if($value<$min||$value>$max)throw new InvalidArgumentException('Giftcode integer out of range');return $value;
}
function giftcode_validate($code,$count,$expired,$detail): array {
 $code=giftcode_canonical($code);$count=giftcode_integer($count,-1);
 if(!is_string($expired)||!preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})[T ]([0-9]{2}:[0-9]{2})(:[0-9]{2})?$/D',$expired,$parts))throw new InvalidArgumentException('Invalid giftcode expiry');
 $normalized=$parts[1].' '.$parts[2].($parts[3]??':00');
 $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$normalized);
 if(!$date||$date->format('Y-m-d H:i:s')!==$normalized||$date<=new DateTimeImmutable()||$normalized>'2038-01-19 03:14:07')throw new InvalidArgumentException('Giftcode expiry must be a valid future TIMESTAMP');
 try{$items=json_decode($detail,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Invalid giftcode JSON');}
 if(!is_array($items)||!array_is_list($items)||count($items)===0||count($items)>200)throw new InvalidArgumentException('Giftcode must contain 1-200 rewards');
 $seen=[];$result=[];
 foreach($items as $item){
  if(!is_array($item))throw new InvalidArgumentException('Invalid giftcode item');
  $id=giftcode_integer($item['id']??null,-3,32767);$quantity=giftcode_integer($item['quantity']??null,1);
  if(isset($seen[$id]))throw new InvalidArgumentException('Duplicate giftcode item');$seen[$id]=true;
  $options=$item['options']??[];if(!is_array($options)||!array_is_list($options)||count($options)>100)throw new InvalidArgumentException('Invalid giftcode options');
  $opseen=[];$ops=[];
  foreach($options as $op){if(!is_array($op))throw new InvalidArgumentException('Invalid giftcode option');$oid=giftcode_integer($op['id']??null,0,32767);$param=giftcode_integer($op['param']??null);
   if(isset($opseen[$oid]))throw new InvalidArgumentException('Duplicate giftcode option');$opseen[$oid]=true;$ops[]=['id'=>$oid,'param'=>$param];}
  $result[]=['id'=>$id,'quantity'=>$quantity,'options'=>$ops];
 }
 return ['code'=>$code,'count'=>$count,'expired'=>$normalized,'detail'=>json_encode($result,JSON_THROW_ON_ERROR)];
}
/** Validate SQL references for both insert and update inside the catalog transaction. */
function giftcode_validate_catalog(mysqli $conn,array $gift): void {
 foreach(json_decode($gift['detail'],true,512,JSON_THROW_ON_ERROR) as $item){
  if($item['id']>=0){$q=$conn->prepare('SELECT type FROM item_template WHERE id=?');$q->bind_param('i',$item['id']);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
   if(!$row)throw new InvalidArgumentException('Unknown giftcode item');
   $id=$item['id'];if((int)$row['type']===13||in_array($id,[74,453,517,518,362],true)||$id>=353&&$id<=360||$id>=372&&$id<=378||$id>=1765&&$id<=1771)throw new InvalidArgumentException('This reward requires unsupported transactional side effects');}
  foreach($item['options'] as $op){$q=$conn->prepare('SELECT id FROM item_option_template WHERE id=?');$q->bind_param('i',$op['id']);$q->execute();$exists=$q->get_result()->fetch_assoc();$q->close();if(!$exists)throw new InvalidArgumentException('Unknown giftcode option');}
 }
}
function giftcode_insert(mysqli $conn,array $gift): void {
 giftcode_validate_catalog($conn,$gift);
 admin_execute_statement('INSERT INTO giftcode(code,count_left,detail,expired) VALUES(?,?,?,?)','siss',[$gift['code'],$gift['count'],$gift['detail'],$gift['expired']]);
 // INSERT acquires the unique catalog identity lock before reading claim history.
 // Any history rejection rolls the INSERT back with the caller's transaction.
 giftcode_assert_update_options(json_decode($gift['detail'],true,512,JSON_THROW_ON_ERROR),giftcode_claim_details($conn,$gift['code']));
}
/** Stable option identity: catalog display order never changes reward semantics. */
function giftcode_sorted_options(array $options): array {
 $result=[];$seen=[];
 if(!array_is_list($options)||count($options)>100)throw new InvalidArgumentException('Invalid delivered options');
 foreach($options as $op){
  if(!is_array($op))throw new InvalidArgumentException('Invalid delivered option');
  $id=giftcode_integer($op['id']??null,0,32767);$param=giftcode_integer($op['param']??null);
  if(isset($seen[$id]))throw new InvalidArgumentException('Duplicate delivered option');
  $seen[$id]=true;$result[]=['id'=>$id,'param'=>$param];
 }
 usort($result,fn($a,$b)=>$a['id']<=>$b['id']);return $result;
}
function giftcode_canonical_detail(string $detail,?int $limit=200): array {
 try{$items=json_decode($detail,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new InvalidArgumentException('Invalid reward history JSON');}
 if(!is_array($items)||!array_is_list($items)||($limit!==null&&count($items)>$limit))throw new InvalidArgumentException('Invalid reward history');
 $seen=[];$result=[];
 foreach($items as $item){
  if(!is_array($item))throw new InvalidArgumentException('Invalid reward history item');
  $id=giftcode_integer($item['id']??null,-3,32767);$quantity=giftcode_integer($item['quantity']??null,1);
  if(isset($seen[$id]))throw new InvalidArgumentException('Duplicate reward history item');$seen[$id]=true;
  $result[]=['id'=>$id,'quantity'=>$quantity,'options'=>giftcode_sorted_options($item['options']??[])];
 }
 usort($result,fn($a,$b)=>$a['id']<=>$b['id']);return $result;
}
function giftcode_fingerprint(array $row): string {
 return hash('sha256',json_encode([giftcode_canonical($row['code']),giftcode_integer($row['count_left'],-1),giftcode_canonical_detail($row['detail']),$row['expired']],JSON_THROW_ON_ERROR));
}
function giftcode_assert_update_options(array $items,array $deliveredDetails): void {
 $incoming=[];foreach($items as $item)$incoming[$item['id']]=giftcode_sorted_options($item['options']??[]);
 foreach($deliveredDetails as $detail){
  if($detail===null)throw new InvalidArgumentException('Historical claim has no reward baseline; this code cannot be edited safely');
  foreach(giftcode_canonical_detail($detail,null) as $old){
   if(isset($incoming[$old['id']])&&$incoming[$old['id']]!==$old['options'])throw new InvalidArgumentException('Cannot change options of previously delivered item #'.$old['id']);
  }
 }
}
/** Caller owns the transaction; claims and quota share the same catalog lock with Java. */
function giftcode_claim_details(mysqli $conn,string $code): array {
 // Current read is essential: template validation can already have established
 // a repeatable-read snapshot before a concurrent claim/delete commits.
 $q=$conn->prepare('SELECT delivered_detail FROM giftcode_claim WHERE canonical_code=? FOR UPDATE');
 $q->bind_param('s',$code);$q->execute();$details=[];$rows=$q->get_result();
 while($row=$rows->fetch_assoc())$details[serialize($row['delivered_detail'])]=$row['delivered_detail'];
 $q->close();return array_values($details);
}
function giftcode_update(mysqli $conn,int $id,array $gift,string $expected): void {
 $q=$conn->prepare('SELECT code,count_left,detail,expired FROM giftcode WHERE id=? FOR UPDATE');
 $q->bind_param('i',$id);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!$row)throw new InvalidArgumentException('Giftcode no longer exists');
 if(!hash_equals(giftcode_fingerprint($row),$expected))throw new InvalidArgumentException('Giftcode or remaining quota changed. Reload the page before editing again');
 if(giftcode_canonical($row['code'])!==$gift['code'])throw new InvalidArgumentException('Giftcode identity cannot change');
 giftcode_assert_update_options(json_decode($gift['detail'],true,512,JSON_THROW_ON_ERROR),giftcode_claim_details($conn,$gift['code']));
 giftcode_validate_catalog($conn,$gift);
 admin_execute_statement('UPDATE giftcode SET count_left=?,detail=?,expired=? WHERE id=?','issi',[$gift['count'],$gift['detail'],$gift['expired'],$id]);
}
