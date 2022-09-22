{{-- Extends layout --}}
@extends('layout.default')



{{-- Content --}}
@section('content')

<div class="container-fluid">
	<div class="form-head mb-sm-5 mb-3 d-flex flex-wrap align-items-center">
		<h2 class="font-w600 title mb-2 me-auto ">{{__('locale.sell_report')}}</h2>
		<div class="weather-btn mb-2">
			<span class="me-3 font-w600 text-black"><i class="fa fa-cloud me-2"></i>21</span>
			<select class="form-control style-1 default-select  me-3 ">
				<option>Medan, IDN</option>
				<option>Jakarta, IDN</option>
				<option>Surabaya, IDN</option>
			</select>
		</div>
		<a href="javascript:void(0);" class="btn btn-secondary mb-2"><i class="las la-calendar scale5 me-3"></i>Filter Periode</a>
	</div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">{{__('locale.sell_report')}}</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example7" class="display" style="min-width: 845px">
                            <thead>
                                <tr>
                                    <th>{{__('locale.time_stamp')}}</th>
                                    <th>{{__('locale.asset_class_purchase')}}</th>
                                    <th>{{__('locale.sell_amount_in_coins')}}</th>
                                    <th>{{__('locale.sell_address_to_send_coin_to')}}</th>
                                    <th>{{__('locale.pay_with')}}</th>
                                    <th>{{__('locale.address_to_pay_to')}}</th>
                                    <th>{{__('locale.chain_stack')}}</th>
                                    <th>{{__('locale.transaction_description')}}</th>
                                    <th>{{__('locale.view_master_loads')}}</th>
                                    <th>{{__('locale.status')}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result as $value)
								<tr>
									<td>{{$value->updated_at}}</td>
                                    <td>
                                        <?php echo $value->asset_purchased == 1? "BTC":"USDT" ?>
                                    </td>
                                    <td>{{$value->sell_amount}}</td>
                                    <td>{{$value->delivered_address}}</td>
                                    <td>{{$value->pay_with}}</td>
                                    <td>{{$value->wallet_address}}</td>
                                    <td>BTC</td>
                                    <td>{{$value->transaction_description}}</td>
                                    <td>
										<a href="{!! url('/masterload_report_sell/'.$value->masterload_id); !!}">View Masterload</a> 
                                    </td>
                                    <td>
										@switch($value->state)
                                            @case (0)
                                                <span class="badge light badge-info">Ordered</span>
                                                @break
                                            @case (1)
                                                <span class="badge light badge-secondary">Master Load</span>
                                                @break
                                            @case (2)
                                                <span class="badge light badge-primary">Super Load</span>
                                                @break
                                            @case (3)
                                                <span class="badge light badge-success">Complete</span>
                                                @break
                                        @endswitch
                                    </td>
                                </tr>
								@endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection	

{{-- Scripts --}}
@section('scripts')
	<script>
		jQuery(document).ready(function(){
			dezSettingsOptions.version = 'dark';
			setTimeout(function() {
				dezSettingsOptions.version = 'dark';
				new dezSettings(dezSettingsOptions);
			}, 1500)
		});
	</script>
@endsection	