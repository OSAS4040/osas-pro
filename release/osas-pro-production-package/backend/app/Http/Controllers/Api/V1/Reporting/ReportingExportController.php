<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CompanyReportingPulseRequest;
use App\Http\Requests\Reporting\CustomerReportingPulseRequest;
use App\Http\Requests\Reporting\GlobalOperationsFeedRequest;
use App\Http\Requests\Reporting\PlatformReportingPulseRequest;
use App\Http\Requests\Reporting\ReportingWorkOrderSummaryRequest;
use App\Models\User;
use App\Reporting\Export\ReportingEnvelopeTabularConverter;
use App\Reporting\Export\ReportingExportGate;
use App\Reporting\Export\ReportingExportStreamResponse;
use App\Services\Reporting\CompanyPulseSummaryReporter;
use App\Services\Reporting\CustomerPulseSummaryReporter;
use App\Services\Reporting\GlobalOperationsFeedReporter;
use App\Services\Reporting\PlatformPulseSummaryReporter;
use App\Services\Reporting\WorkOrderOperationalSummaryReporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * WAVE 2 / PR12 — read-only exports for unified reporting JSON endpoints (no legacy /reports/*).
 */
final class ReportingExportController extends Controller
{
    public function workOrderSummary(
        ReportingWorkOrderSummaryRequest $request,
        WorkOrderOperationalSummaryReporter $reporter,
        ReportingEnvelopeTabularConverter $converter,
    ): Response {
        $user = $this->actor($request);
        $format = $this->validatedFormat($request);
        $payload = $reporter->build($user, $request->validated());
        $rows = $converter->toRows($payload);

        return ReportingExportStreamResponse::make('operations.work_order_summary', $format, $rows);
    }

    public function companyPulseSummary(
        CompanyReportingPulseRequest $request,
        CompanyPulseSummaryReporter $reporter,
        ReportingEnvelopeTabularConverter $converter,
    ): Response {
        $user = $this->actor($request);
        $format = $this->validatedFormat($request);
        $payload = $reporter->build($user, $request->validated());
        $rows = $converter->toRows($payload);

        return ReportingExportStreamResponse::make('company.pulse_summary', $format, $rows);
    }

    public function customerPulseSummary(
        CustomerReportingPulseRequest $request,
        CustomerPulseSummaryReporter $reporter,
        ReportingEnvelopeTabularConverter $converter,
    ): Response {
        $user = $this->actor($request);
        $format = $this->validatedFormat($request);
        $payload = $reporter->build($user, $request->validated());
        $rows = $converter->toRows($payload);

        return ReportingExportStreamResponse::make('customer.pulse_summary', $format, $rows);
    }

    public function globalOperationsFeed(
        GlobalOperationsFeedRequest $request,
        GlobalOperationsFeedReporter $reporter,
        ReportingEnvelopeTabularConverter $converter,
    ): Response {
        $user = $this->actor($request);
        $format = $this->validatedFormat($request);
        $cap = (int) config('reporting.export.max_rows', 500);
        $payload = $reporter->build($user, $request->validated(), $cap);
        $rows = $converter->toRows($payload);

        return ReportingExportStreamResponse::make('operations.global_feed', $format, $rows);
    }

    public function platformPulseSummary(
        PlatformReportingPulseRequest $request,
        PlatformPulseSummaryReporter $reporter,
        ReportingEnvelopeTabularConverter $converter,
    ): Response {
        $user = $this->actor($request);
        $format = $this->validatedFormat($request);
        $payload = $reporter->build($user, $request->validated());
        $rows = $converter->toRows($payload);

        return ReportingExportStreamResponse::make('platform.pulse_summary', $format, $rows);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        return $user;
    }

    private function validatedFormat(Request $request): string
    {
        ReportingExportGate::ensureEnabled();
        $formats = ReportingExportGate::formats();
        $validated = Validator::make($request->query(), [
            'format' => ['required', 'string', Rule::in($formats)],
        ])->validate();

        return strtolower((string) $validated['format']);
    }
}
