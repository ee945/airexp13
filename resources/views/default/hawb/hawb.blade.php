@extends(theme('layout.layout'))

@section('container')
  <div class="container theme-showcase" role="main">
    <div class="row">
      <div class="col-md-12">
        <style>
          .mawbtab tr td{padding:5px;}
        </style>
        {!! Form::open() !!}
        @if($errors->any())
        <div class="alert alert-danger text-left" style="width:880px;padding-bottom: 5px;margin:0 auto 10px auto" role="alert">
          <div style="margin:0 auto;">
            <ul>
              @foreach ($errors->all() as $error)
              <li><strong>{{ $error }}</strong></li>
              @endforeach
            </ul>
          </div>
        </div>
        @elseif(isset($_GET['save']))
        <div class="alert alert-success text-center" style="width:880px;padding:8px 0px;margin:0 auto 10px auto" role="alert">
          <strong>保存成功！</strong>
          <a href="{{ route('print_hawb',['hawb'=>$hawb->hawb])}}" target="_blank" type="button" class="btn btn-success" style="width:80px;margin-left: 20px;">打印</a>
        </div>
        @endif
        <table class="mawbtab" border="1" cellspacing="0" cellpadding="0" style="font-size:12px;border-collapse:collapse;margin:0 auto">
          <colgroup>
            <col width="220px" />
            <col width="220px" />
            <col width="420px" />
            <col width="10px" />
            <col width="10px" />
          </colgroup>
          <tr>
            <td colspan="2" height="20" style="font-size:20px;">MAWB <span style="color:#000000;font-size:20px;">{!! $hawb->mawb !!}</span></td>
            <td colspan="3" height="20" align="right" style="font-size:20px;">HAWB  <span style="color:#000000;font-size:20px;">{!! env('CONF_HAWB_PREFIX') !!}{!! Form::text('hawb',$hawb->hawb,['hidden'=>'hidden']) !!}{!! $hawb->hawb !!}</span></td>
          </tr>
          <tr>
            <td height="20" style="border-bottom: 0px;">Shipper's Name and Address</td>
            <td>{!! Form::text('shippercode',null,['size'=>'8']) !!}</td>
            <td colspan="3" rowspan="2">
              <span style="float:left;">AIR WAYBILL</span>&nbsp;<br />Issued by<br />
              <span style="float:left;white-space: pre-line;">{!! str_replace('\n',"\n",env("CONF_AGENT_ADDR","COMPANY NAME\nADDRESS")) !!}</span>
            </td>
          </tr>
          <tr>
            <td height="90" colspan="2" style="border-top: 0px;">
              {!! Form::textarea('shipper',isset($hawb->shipper)?$hawb->shipper:null,['rows'=>5,'cols'=>45]) !!}
            </td>
          </tr>
          <tr>
            <td height="20" style="border-bottom: 0px;">Consignee's Name and Address*</td>
            <td>
              {!! Form::text('consigneecode',null,['size'=>'8']) !!}
            </td>
            <td colspan="3" rowspan="2">&nbsp;</td>
          </tr>
          <tr>
            <td height="90" colspan="2" style="border-top: 0px;">
              {!! Form::textarea('consignee',isset($hawb->consignee)?$hawb->consignee:null,['rows'=>5,'cols'=>45]) !!}
            </td>
          </tr>
          <tr>
            <td height="45" colspan="2">Issuing Carrier's Agent Name and City<br />
              {!! ($hawb->agentabbr!="")?$hawb->agentabbr:$agentabbr !!}
            </td>
            <td colspan="3" rowspan="3">
              Accounting Information<br />FREIGHT PREPAID<br />
              Also Notify
              {!! Form::text('notifycode',null,['size'=>'8']) !!}<br>
              {!! Form::textarea('notify',($hawb->notify!="")?$hawb->notify:$notify,['rows'=>5,'cols'=>45]) !!}
            </td>
          </tr>
          <tr>
            <td height="40">Agent's IATA Code<br />
              &nbsp;
            </td>
            <td>Account NO.<br />
              &nbsp;
            </td>
          </tr>
          <tr>
            <td height="40" colspan="2">Airport of Departure(addr. OF First Carrier) and Requested Routing<br />
              {!! Form::text('depar',($hawb->depar!="")?$hawb->depar:$depar,['size'=>'20']) !!}
            </td>
          </tr>
            <tr>
              <td colspan="2" style="padding:0">
              <table width="100%" frame="void" rules="all" border="1" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="15%" height="20">To</td>
                  <td width="35%">By First Carrier</td>
                  <td width="15%">to</td>
                  <td width="10%">by</td>
                  <td width="15%">to</td>
                  <td>by</td>
                </tr>
                <tr>
                  <td height="25">{!! Form::text('dest',$hawb->dest,['size'=>'3','readonly'=>'readonly']) !!}</td>
                  <td>{!! Form::text('fltprefix',substr($hawb->fltno,0,2),['size'=>'3','readonly'=>'readonly']) !!}</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                  <td>&nbsp;</td>
                </tr>
              </table>
              </td>
              <td colspan="3" style="padding:0">
              <table width="100%" frame="void" rules="all" border="1" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="8%" height="20">Currency</td>
                  <td width="6%">CHGS</td>
                  <td colspan="2" width="14%" align="center">WT/VAL</td>
                  <td colspan="2" width="14%" align="center">Other</td>
                  <td width="24%">D.V.for Carriage</td>
                  <td>D.V.for Custom</td>
                </tr>
                <tr>
                  <td height="25">{!! Form::text('curr',($hawb->curr!="")?$hawb->curr:$curr,['size'=>'4']) !!}</td>
                  <td>&nbsp;</td>
                  <td width="7%">{{ $wtp }}</td>
                  <td width="7%">{{ $wtc }}</td>
                  <td width="7%">{{ $otp }}</td>
                  <td width="7%">{{ $otc }}</td>
                  <td>{!! Form::text('nvd',($hawb->nvd!="")?$hawb->nvd:$nvd,['size'=>'4']) !!}</td>
                  <td>{!! Form::text('ncv',($hawb->ncv!="")?$hawb->ncv:$ncv,['size'=>'4']) !!}</td>
                </tr>
              </table>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="padding:0">
              <table width="100%" frame="void" rules="all" border="1" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="24%" height="20">Airport of Dest.</td>
                  <td width="23%" style="border-bottom:0px;"></td>
                  <td colspan="2" width="30%" align="center">Requested Flt Date</td>
                  <td width="23%" style="border-bottom:0px;"></td>
                </tr>
                <tr>
                  <td height="25">{!! Form::text('desti',isset($hawb->desti)?$hawb->desti:null,['size'=>'16']) !!}</td>
                  <td colspan="2" style="border-top:0px;">{!! Form::text('fltno',isset($hawb->fltno)?$hawb->fltno:null,['size'=>'6']) !!}</td>
                  <td colspan="2" style="border-top:0px;">{!! Form::text('fltdate',isset($hawb->fltdate)?$hawb->fltdate:null,['size'=>'10']) !!}</td>
                </tr>
              </table>
              </td>
              <td colspan="3">Amount of Insurance</td>
            </tr>
            <tr>
              <td height="50" colspan="5">Handling Information<br />
              {!! Form::textarea('special',isset($hawb->special)?$hawb->special:null,['rows'=>2,'cols'=>100]) !!}
              </td>
            </tr>
            <tr>
              <td colspan="5" style="padding:0">
              <table width="100%" frame="void" rules="all" border="1" cellspacing="0" cellpadding="0">
                <tr align="center">
                  <td width="8%" height="30">No of Pieces</td>
                  <td width="14%">Gross Weight</td>
                  <td width="5%">KG<br>lb</td>
                  <td width="5%">Rate Class</td>
                  <td width="14%">Chargeable Weight</td>
                  <td width="10%">Rate/Charge</td>
                  <td width="14%">Total</td>
                  <td width="30%">Nature and Quantity of Goods</td>
                </tr>
                <tr>
                  <td height="25" style="border-bottom:0px;">
                    {!! Form::text('package',isset($hawb->package)?$hawb->package:null,['size'=>'3']) !!}
                  </td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td style="border-bottom:0px;">&nbsp;</td>
                  <td rowspan="3" align="center" valign="top">
                  {!! Form::textarea('cgodescp',isset($hawb->cgodescp)?$hawb->cgodescp:null,['rows'=>12,'cols'=>50]) !!}<br />
                  {!! Form::text('cbm',isset($hawb->cbm)?round($hawb->cbm,3):null,['size'=>'6']) !!}&nbsp;CBM
{{--                   <input type='button' name="getcbm" value="CBM"/>
                  <input type='button' name="getres" value="RES"/> --}}
                  {{-- <button name="getres">RES</button> --}}
                  </td>
                </tr>
                <tr>
                  <td height="150" valign="top" style="border-top:0px;">
                    {!! Form::text('num',isset($hawb->num)?$hawb->num:null,['size'=>'3']) !!}
                  </td>
                  <td valign="top" style="border-top:0px;">
                    {!! Form::text('gw',isset($hawb->gw)?$hawb->gw:null,['size'=>'5']) !!}
                  </td>
                  <td valign="top" style="border-top:0px;border-bottom:0px;">K.</td>
                  <td valign="top" style="border-top:0px;border-bottom:0px;">
                    {!! Form::text('rclass',isset($hawb->rclass)?$hawb->rclass:null,['size'=>'1']) !!}
                  </td>
                  <td valign="top" style="border-top:0px;border-bottom:0px;">
                    {!! Form::text('cw',isset($hawb->cw)?$hawb->cw:null,['size'=>'5','readonly'=>'readonly']) !!}
                  </td>
                  <td valign="top" style="border-top:0px;border-bottom:0px;">&nbsp;</td>
                  <td valign="top" style="border-top:0px;">&nbsp;</td>
                </tr>
                <tr>
                  <td style="border-top:0px;" height="25">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                  <td style="border-top:0px;">&nbsp;</td>
                </tr>
              </table>
              </td>
            </tr>
            <tr>
              <td height="300" colspan="5" style="padding:0">
              <table width="100%" frame="void" rules="all" border="1" cellspacing="0" cellpadding="0">
                <colgroup>
                  <col width="100px" />
                  <col width="100px" />
                  <col width="100px" />
                  <col width="100px" />
                  <col width="600px" />
                </colgroup>
                <tr>
                  <td width="10%" height="20" align="center">Prepaid</td>
                  <td colspan="2" width="20%" align="center">Weight Charge</td>
                  <td width="10%" align="center">Collect</td>
                  <td width="60%" rowspan="6" valign="top">Other Charges</td>
                </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="20">&nbsp;</td>
                  <td colspan="2">Valuation Charge</td>
                  <td>&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="20">&nbsp;</td>
                  <td colspan="2">Tax</td>
                  <td>&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr>
                  <td height="20" align="center">&nbsp;</td>
                  <td colspan="2" align="center">Total Other Charge Due Agent</td>
                  <td align="center">&nbsp;</td>
                  <td rowspan="5" align="center">Shipper certifies that the particulars on the face hereof are correct and that insofar as any part of the consignment contains dangerous goods,such part is properly described by name and is in proper condition for carriage by air according to the applicable Dangerous Goods Regulations.<br /><br />
                  {!! Form::text('agentabbr',($hawb->agentabbr!="")?$hawb->agentabbr:$agentabbr,['size'=>'12']) !!}<br /><br />
                  Signature of shipper or his Agent
                  </td>
                </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="20">&nbsp;</td>
                  <td colspan="2">Total Other Charge Due Carrier</td>
                  <td>&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>  {{-- Total Other Charge --}}
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="20" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr>
                  <td height="20" colspan="2" align="center">Total Prepaid</td>
                  <td colspan="2" align="center">Total Collect</td>
                  <td rowspan="4" align="center">
                  <table width="70%">
                    <tr>
                      <td width="33%">{!! Form::text('opdate',isset($hawb->opdate)?$hawb->opdate:null,['size'=>'12']) !!}</td>
                      <td width="33%">{!! ($hawb->depar!="")?$hawb->depar:$depar !!}</td>
                      <td>&nbsp;</td>
                    </tr>
                    <tr>
                      <td>Executed On date</td>
                      <td>at place</td>
                      <td>Signature</td>
                    </tr>
                  </table>
                  </td>
                </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>  {{-- Total Prepaid --}}
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr align="center">
                  <td height="20" colspan="2">Currency Rate</td>
                  <td colspan="2">CC charges in Dest.</td>
                  </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                  </tr>
                <tr>
                  <td height="20" colspan="2" align="center">Charges at Dest</td>
                  <td colspan="2" align="center">Total Collect Charges</td>
                  <td rowspan="2" align="right" style="font-size:20px;">{!! env('CONF_HAWB_PREFIX') !!}{{ $hawb->hawb }}</td>
                </tr>
                <tr align="center">
                  <td height="25" colspan="2">&nbsp;</td>
                  <td colspan="2">&nbsp;</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
        <div class="text-center" style="padding:10px 0px;">
          {!! Form::submit('保存',['class'=>'btn btn-primary form-control','style'=>'width:80px;']) !!}
          <a href="{{ route('print_hawb',['hawb'=>$hawb->hawb])}}" target="_blank" type="button" class="btn btn-success" style="width:80px;margin-left: 20px;">打印</a>
        </div>
        {!! Form::close() !!}
      </div>
    </div>
  </div>

  <script src="/js/jquery.min.js"></script>
  <script type="text/javascript">
    // $(document).ready(function(){
    //   $(":text").attr("style",'text-transform:uppercase;');
    //   $("textarea").attr("style",'text-transform:uppercase;');
    // });
    $(document).ready(function(){
      $(":text").attr("onkeyup",'this.value=this.value.toUpperCase()');
      $("textarea").attr("onkeyup",'this.value=this.value.toUpperCase()');
    });
    //输入体积自动处理收费重量
    $("input[name='cbm']").blur(function(){
      cbmw=$("input[name='cbm']").val()/0.006;
      gw=$("input[name='gw']").val();
      cw=Math.round((cbmw>=gw)?cbmw:gw);
      $("input[name='cw']").attr("value",cw);
    });

    //输入实际重量自动处理收费重量
    $("input[name='gw']").blur(function(){
      cbmw=$("input[name='cbm']").val()/0.006;
      gw=$("input[name='gw']").val();
      cw=Math.round((cbmw>=gw)?cbmw:gw);
      $("input[name='cw']").attr("value",cw);
    });

    //输入发货人代码自动显示补全地址
    $("input[name='shippercode']").blur(function(){
      var shippercode = $("input[name='shippercode']").val();
      if(shippercode!=''){
        $.get('{{url('get/hshipper')}}/'+shippercode,function(json){
          if(json.addr==null)json.addr=='';
          $("textarea[name='shipper']").val(json.addr);
        });
      }
    });

    //输入收货人代码自动显示补全地址
    $("input[name='consigneecode']").blur(function(){
      var consigneecode = $("input[name='consigneecode']").val();
      if(consigneecode!=''){
        $.get('{{url('get/hconsignee')}}/'+consigneecode,function(json){
          if(json.addr==null)json.addr=='';
          $("textarea[name='consignee']").val(json.addr);
        });
      }
    });

    //输入通知人代码自动显示补全地址
    $("input[name='notifycode']").blur(function(){
      var notifycode = $("input[name='notifycode']").val();
      if(notifycode!=''){
        $.get('{{url('get/hnotify')}}/'+notifycode,function(json){
          if(json.addr==null)json.addr=='';
          $("textarea[name='notify']").val(json.addr);
        });
      }
    });
  </script>
@stop