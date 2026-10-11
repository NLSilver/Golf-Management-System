@extends('layouts.app')

@section('title', 'Facility Settings')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="mb-3">
        <h4 class="mb-0 fw-bold">Facility Settings</h4>
        <div class="text-muted small">Values used across the system: receipt header, operating hours, billing rules.</div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Facility identity</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Facility name</label>
                            <input type="text" class="form-control" name="facility_name" required
                                   value="{{ old('facility_name', $settings['facility_name'] ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="facility_address"
                                   value="{{ old('facility_address', $settings['facility_address'] ?? '') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Receipt footer</label>
                            <input type="text" class="form-control" name="receipt_footer" maxlength="255"
                                   value="{{ old('receipt_footer', $settings['receipt_footer'] ?? '') }}">
                            <div class="form-text">Printed at the bottom of every official receipt.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold">Hours &amp; billing rules</div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Opens</label>
                                <input type="time" class="form-control" name="operating_open" required
                                       value="{{ old('operating_open', $settings['operating_open'] ?? '06:00') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Closes</label>
                                <input type="time" class="form-control" name="operating_close" required
                                       value="{{ old('operating_close', $settings['operating_close'] ?? '22:00') }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Minimum session (minutes)</label>
                                <input type="number" class="form-control" name="minimum_session_minutes"
                                       min="15" max="720" required
                                       value="{{ old('minimum_session_minutes', $settings['minimum_session_minutes'] ?? 90) }}">
                                <div class="form-text">Paper rule: 1h 30m (90 minutes).</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">"Soon to expire" alert (minutes)</label>
                                <input type="number" class="form-control" name="expiring_soon_minutes"
                                       min="1" max="120" required
                                       value="{{ old('expiring_soon_minutes', $settings['expiring_soon_minutes'] ?? 10) }}">
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label">Overstay grace before auto-close (minutes)</label>
                                <input type="number" class="form-control" name="overstay_grace_minutes"
                                       min="0" max="60" required
                                       value="{{ old('overstay_grace_minutes', $settings['overstay_grace_minutes'] ?? 5) }}">
                                <div class="form-text">Sessions this far past their end time close automatically and leave an unpaid receipt at the front desk.</div>
                            </div>
                        </div>
                        <div class="form-text">
                            Bay rate and add-on prices are managed in the services rate card.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success">Save settings</button>
            <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
