@extends('layouts.generic')

@section('page_title', __('PIX Payment'))

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0 text-center pt-4">
                        <h4 class="font-weight-bold">{{__('PIX Payment')}}</h4>
                        <p class="text-muted">{{__('Scan the QR Code below to pay')}}</p>
                    </div>
                    <div class="card-body text-center pb-5">
                        <div class="mb-4">
                            <img src="data:image/png;base64,{{$transaction->asaas_pix_qr_code}}" alt="PIX QR Code"
                                class="img-fluid shadow-sm rounded" style="max-width: 250px;">
                        </div>

                        <div class="form-group mb-4 text-left">
                            <label
                                class="text-muted small text-uppercase font-weight-bold mb-2 d-block text-center">{{__('Or copy and paste the code below')}}</label>
                            <div class="input-group">
                                <input type="text" id="pix-payload" class="form-control bg-light border-0"
                                    value="{{$transaction->asaas_pix_payload}}" readonly
                                    style="height: 50px; font-size: 14px;">
                                <div class="input-group-append">
                                    <button class="btn btn-primary px-4" onclick="copyPixCode()">
                                        {{__('Copy')}}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info border-0 rounded-lg small">
                            {{__('After payment, your balance will be updated automatically. This may take a few minutes.')}}
                        </div>

                        <a href="{{route('my.settings', ['type' => 'wallet'])}}"
                            class="btn btn-outline-primary btn-block mt-4">
                            {{__('Go to Wallet')}}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyPixCode() {
            var copyText = document.getElementById("pix-payload");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");

            alert("{{__('Code copied to clipboard!')}}");
        }
    </script>
@endsection