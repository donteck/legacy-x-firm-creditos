<?php
if (!defined('ABSPATH')) exit;

class CreditOS_Report_Parser {
    public function parse($path, $format, $bureau = 'multi') {
        if (!$path || !file_exists($path)) return new WP_Error('creditos_parser_file_missing', 'The uploaded credit report file could not be found.');
        $format = strtolower((string) $format);
        if ($format === 'pdf') return $this->parse_pdf($path, $bureau);
        if ($format === 'csv') return $this->parse_csv($path, $bureau);
        return new WP_Error('creditos_parser_format', 'Unsupported parser format.');
    }

    private function parse_pdf($path, $bureau) {
        $bin = $this->find_pdftotext();
        if (!$bin) return new WP_Error('creditos_pdf_needs_ocr', 'PDF text extraction is not available on this server.');
        $text = $this->run_pdftotext($bin, $path, '-layout');
        $method = 'layout';
        if (!$text || !$this->looks_like_credit_report($text)) {
            $text = $this->run_pdftotext($bin, $path, '-raw');
            $method = 'raw';
        }
        if (!$text || !$this->looks_like_credit_report($text)) return new WP_Error('creditos_pdf_needs_ocr', 'CreditOS could not recover enough structured text from this PDF.');
        $detected = $this->detect_bureau($text, $bureau);
        $normalized = $this->normalize_text($text, $detected);
        return array('status'=>'normalized','bureau'=>$detected,'text_length'=>strlen($text),'extraction_method'=>$method,'account_block_count'=>count($this->account_blocks($text)),'normalized'=>$normalized);
    }

    private function normalize_text($text, $bureau) {
        if ($bureau === 'experian' || stripos($text, 'Experian') !== false) return $this->normalize_experian($text);
        return array('tradelines'=>array(),'collections'=>array(),'inquiries'=>array(),'personal_information'=>$this->parse_basic_personal($text,$bureau));
    }

    private function normalize_experian($text) {
        $tradelines = array();
        foreach ($this->account_blocks($text) as $block) {
            $name = $this->line_value($block, 'Account Name');
            $number = $this->line_value($block, 'Account Number');
            if (!$name || (!$number && stripos($block, 'Account Type') === false)) continue;
            $status = $this->multiline_value($block, 'Status', array('Status Updated','Balance','Balance Updated'), 3);
            $tradelines[] = array(
                'creditor_name'=>$name,'bureau'=>'experian','account_number_masked'=>$number,
                'account_type'=>$this->line_value($block,'Account Type'),'responsibility'=>$this->line_value($block,'Responsibility'),
                'opened_date'=>$this->line_value($block,'Date Opened'),'status'=>$status,
                'balance'=>$this->number($this->line_value($block,'Balance')),'credit_limit'=>$this->number($this->line_value($block,'Credit Limit')),
                'past_due'=>$this->past_due($status),'payment_status'=>$this->payment_status($block,$status),
                'date_reported'=>$this->line_value($block,'Balance Updated'),'remarks'=>$this->remarks($block)
            );
        }
        return array('tradelines'=>$tradelines,'collections'=>array(),'inquiries'=>$this->parse_hard_inquiries($text),'personal_information'=>$this->parse_basic_personal($text,'experian'));
    }

    private function account_blocks($text) {
        $blocks=array();
        if (!preg_match_all('/^[ \t]*Account\s+Name[ \t]+.+$/mi',$text,$matches,PREG_OFFSET_CAPTURE)) return $blocks;
        $hits=$matches[0];
        for($i=0,$n=count($hits);$i<$n;$i++){
            $start=$hits[$i][1]; $end=($i+1<$n)?$hits[$i+1][1]:strlen($text); $block=substr($text,$start,$end-$start);
            if(stripos($block,'Account Number')!==false && stripos($block,'Account Type')!==false) $blocks[]=$block;
        }
        return $blocks;
    }

    private function line_value($block,$label) {
        $pattern='/^[ \t]*'.preg_quote($label,'/').'[ \t]*(?:[:\-][ \t]*)?(.+?)[ \t]*$/mi';
        if(!preg_match($pattern,$block,$m)) return '';
        $value=trim(preg_replace('/[ \t]+/',' ',$m[1]));
        return $value==='-'?'':$value;
    }

    private function multiline_value($block,$label,$stop_labels,$max_lines=3) {
        $lines=preg_split('/\n/',$block); $out=array(); $capture=false;
        foreach($lines as $line){
            if(!$capture){if(preg_match('/^[ \t]*'.preg_quote($label,'/').'[ \t]+(.+)$/i',$line,$m)){$out[]=trim($m[1]);$capture=true;}continue;}
            foreach($stop_labels as $stop) if(preg_match('/^[ \t]*'.preg_quote($stop,'/').'\b/i',$line)) return trim(preg_replace('/\s+/',' ',implode(' ',$out)));
            $part=trim($line); if($part!=='')$out[]=$part; if(count($out)>=$max_lines)break;
        }
        return trim(preg_replace('/\s+/',' ',implode(' ',$out)));
    }

    private function past_due($status){return preg_match('/\$([0-9,]+(?:\.\d{2})?)\s+past due/i',(string)$status,$m)?$this->number($m[1]):null;}
    private function payment_status($block,$status){if(stripos($status,'charged off')!==false)return'Charge Off';if(preg_match('/\b(30|60|90|120|150|180) days past due\b/i',$block,$m))return$m[1].' Days Past Due';if(stripos($status,'paid')!==false&&stripos($status,'closed')!==false)return'Paid / Closed';if(stripos($status,'open')!==false)return'Open';return$status;}
    private function remarks($block){$r=array();$until=$this->line_value($block,'On Record Until');if($until)$r[]='On Record Until '.$until;return implode('; ',array_unique($r));}
    private function parse_basic_personal($text,$bureau){$out=array();if(preg_match('/Prepared For\s*[:\-]?\s*\n?\s*([^\n]{3,80})/i',$text,$m))$out[]=array('info_type'=>'name','info_value'=>trim($m[1]),'bureau'=>$bureau);if(preg_match('/Date Generated\s*[:\-]?\s*([A-Za-z]{3,9}\s+\d{1,2},\s+\d{4})/i',$text,$m))$out[]=array('info_type'=>'report_date','info_value'=>trim($m[1]),'bureau'=>$bureau);return$out;}
    private function parse_hard_inquiries($text){if(preg_match('/\b0\s+Hard Inquiries\b/i',$text))return array();return array();}
    private function looks_like_credit_report($text){if(strlen((string)$text)<120)return false;$hits=0;foreach(array('Account Name','Account Number','Experian','Equifax','TransUnion','Credit Report','Prepared For')as$needle)if(stripos($text,$needle)!==false)$hits++;return$hits>=2;}
    private function detect_bureau($text,$fallback){if(stripos($text,'Annual Credit Report - Experian')!==false||stripos($text,'usa.experian.com/acr/')!==false||(stripos($text,'Prepared For')!==false&&stripos($text,'Experian')!==false))return'experian';if(stripos($text,'Equifax')!==false&&stripos($text,'Experian')===false)return'equifax';if(stripos($text,'TransUnion')!==false&&stripos($text,'Experian')===false)return'transunion';return in_array($fallback,array('experian','equifax','transunion','multi'),true)?$fallback:'multi';}
    private function run_pdftotext($bin,$path,$mode){$tmp=wp_tempnam('creditos.txt');if(!$tmp)return'';$cmd=escapeshellarg($bin).' '.escapeshellarg($mode).' -enc UTF-8 '.escapeshellarg($path).' '.escapeshellarg($tmp).' 2>&1';$output=array();$code=1;@exec($cmd,$output,$code);$text=($code===0&&file_exists($tmp))?@file_get_contents($tmp):'';@unlink($tmp);return$this->clean_text($text);}
    private function clean_text($text){$text=str_replace("\0",'',(string)$text);$text=preg_replace("/\r\n?|\r/","\n",$text);$text=preg_replace('/[ \t]+$/m','',$text);return trim($text);}
    private function parse_csv($path,$bureau){$h=fopen($path,'r');if(!$h)return new WP_Error('creditos_csv_open','Could not read CSV.');$head=fgetcsv($h);if(!$head){fclose($h);return new WP_Error('creditos_csv_header','Unreadable CSV header.');}$head=array_map(function($v){return sanitize_key(str_replace(array(' ','-'),'_',strtolower(trim($v))));},$head);$rows=array();while(($x=fgetcsv($h))!==false){$x=array_pad($x,count($head),'');$r=array_combine($head,array_slice($x,0,count($head)));$name=isset($r['creditor_name'])?$r['creditor_name']:(isset($r['creditor'])?$r['creditor']:(isset($r['account_name'])?$r['account_name']:''));if($name)$rows[]=array('creditor_name'=>$name,'bureau'=>isset($r['bureau'])?$r['bureau']:$bureau,'account_number_masked'=>isset($r['account_number'])?$r['account_number']:'','account_type'=>isset($r['account_type'])?$r['account_type']:'','balance'=>$this->number(isset($r['balance'])?$r['balance']:null),'credit_limit'=>$this->number(isset($r['credit_limit'])?$r['credit_limit']:null),'status'=>isset($r['status'])?$r['status']:'');}fclose($h);return array('status'=>'normalized','bureau'=>$bureau,'normalized'=>array('tradelines'=>$rows,'collections'=>array(),'inquiries'=>array(),'personal_information'=>array()));}
    private function find_pdftotext(){foreach(array('/usr/bin/pdftotext','/usr/local/bin/pdftotext')as$path)if(is_executable($path))return$path;return false;}
    private function number($value){if($value===null||trim((string)$value)===''||trim((string)$value)==='-')return null;$clean=preg_replace('/[^0-9.\-]/','',(string)$value);return is_numeric($clean)?(float)$clean:null;}
    public function safe_excerpt($text,$limit=800){$text=preg_replace('/\b\d{3}-\d{2}-\d{4}\b/','***-**-****',(string)$text);$text=preg_replace('/\b\d{9,16}\b/','********',$text);return mb_substr($text,0,$limit);}
}
