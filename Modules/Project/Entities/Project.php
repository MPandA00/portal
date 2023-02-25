<?php

namespace Modules\Project\Entities;

use App\Traits\Filters;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Client\Entities\Client;
use Modules\EffortTracking\Entities\Task;
use Modules\Invoice\Entities\Invoice;
use Modules\Invoice\Entities\LedgerAccount;
use Modules\Invoice\Services\InvoiceService;
use Modules\Project\Database\Factories\ProjectFactory;
use Modules\User\Entities\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Carbon\Carbon;

class Project extends Model implements Auditable
{
    use HasFactory, Filters, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $table = 'projects';

    protected $guarded = [];

    protected $dates = ['start_date', 'end_date'];

    protected static function newFactory()
    {
        return new ProjectFactory();
    }

    public function scopeIsAMC($query, $isAmc)
    {
        $query->where('is_amc', $isAmc);
    }

    public function scopeLinkedToTeamMember($query, $userId)
    {
        return $query->whereHas('getTeamMembers', function ($query) use ($userId) {
            $query->where('team_member_id', $userId);
        });
    }

    public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'project_team_members', 'project_id', 'team_member_id')
            ->withPivot('designation', 'ended_on', 'id', 'daily_expected_effort', 'billing_engagement', 'started_on', 'ended_on')->withTimestamps()->whereNull('project_team_members.ended_on');
    }

    public function repositories()
    {
        return $this->hasMany(ProjectRepository::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeStatus($query, $status)
    {
        return $query->whereStatus($status);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function getTeamMembers()
    {
        return $this->hasMany(ProjectTeamMember::class)->whereNULL('ended_on');
    }

    public function getTeamMembersGroupedByEngagement()
    {
        return $this->getTeamMembers()->select('billing_engagement', DB::raw('count(*) as resource_count'))->groupBy('billing_engagement')->get();
    }

    public function getInactiveTeamMembers()
    {
        return $this->hasMany(ProjectTeamMember::class)->whereNotNull('ended_on')->orderBy('ended_on', 'DESC');
    }

    public function getAllTeamMembers()
    {
        return $this->hasMany(ProjectTeamMember::class);
    }

    public function projectContracts()
    {
        return $this->hasMany(ProjectContract::class);
    }

    public function getExpectedHoursInMonthAttribute($startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? $this->client->month_start_date;
        $endDate = $endDate ?? $this->client->month_end_date;
        $daysInMonth = count($this->getWorkingDaysList($startDate, $endDate));
        $teamMembers = $this->getTeamMembers()->get();
        $currentExpectedEffort = 0;

        foreach ($teamMembers as $teamMember) {
            $currentExpectedEffort += $teamMember->daily_expected_effort * $daysInMonth;
        }

        return round($currentExpectedEffort, 2);
    }

    public function getHoursBookedForMonth($monthToSubtract = 1, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?: $this->client->getMonthStartDateAttribute($monthToSubtract);
        $endDate = $endDate ?: $this->client->getMonthEndDateAttribute($monthToSubtract);

        return $this->getAllTeamMembers->sum(function ($teamMember) use ($startDate, $endDate) {
            if (! $teamMember->projectTeamMemberEffort) {
                return 0;
            }

            return $teamMember->projectTeamMemberEffort()
                ->where('added_on', '>=', $startDate)
                ->where('added_on', '<=', $endDate)
                ->sum('actual_effort');
        });
    }

    public function getVelocityForMonthAttribute($monthToSubtract, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ?? $this->client->month_start_date;
        $endDate = $endDate ?? $this->client->month_end_date;

        return $this->getExpectedHoursInMonthAttribute($startDate, $endDate) ? round($this->getHoursBookedForMonth($monthToSubtract, $startDate, $endDate) / ($this->getExpectedHoursInMonthAttribute($startDate, $endDate)), 2) : 0;
    }

    public function getCurrentHoursForMonthAttribute()
    {
        $teamMembers = $this->getTeamMembers()->get();
        $totalEffort = 0;

        foreach ($teamMembers as $teamMember) {
            $totalEffort += $teamMember->projectTeamMemberEffort->whereBetween('added_on', [$this->client->month_start_date->subday(), $this->client->month_end_date])->sum('actual_effort');
        }

        return $totalEffort;
    }

    public function getVelocityAttribute()
    {
        return $this->current_expected_hours ? round($this->current_hours_for_month / $this->current_expected_hours, 2) : 0;
    }

    public function getWorkingDaysList($startDate, $endDate)
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $dates = [];
        $weekend = ['Saturday', 'Sunday'];
        foreach ($period as $date) {
            if (! in_array($date->format('l'), $weekend)) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }

    public function getCurrentExpectedHoursAttribute()
    {
        $currentDate = today(config('constants.timezone.indian'));

        if (now(config('constants.timezone.indian'))->format('H:i:s') < config('efforttracking.update_date_count_after_time')) {
            $currentDate = $currentDate->subDay();
        }

        return $this->getExpectedHours($currentDate);
    }

    public function getExpectedHoursTillTodayAttribute()
    {
        return $this->getExpectedHours(today(config('constants.timezone.indian')));
    }

    public function getExpectedHours($currentDate)
    {
        $teamMembers = $this->getTeamMembers()->get();
        $daysTillToday = count($this->getWorkingDaysList($this->client->month_start_date, $currentDate));
        $currentExpectedEffort = 0;

        foreach ($teamMembers as $teamMember) {
            $currentExpectedEffort += $teamMember->daily_expected_effort * $daysTillToday;
        }

        return round($currentExpectedEffort, 2);
    }

    public function getExpectedMonthlyHoursAttribute()
    {
        $teamMembers = $this->getTeamMembers()->get();
        $workingDaysCount = count($this->getWorkingDaysList($this->client->month_start_date, $this->client->month_end_date));
        $expectedMonthlyHours = 0;

        foreach ($teamMembers as $teamMember) {
            $expectedMonthlyHours += $teamMember->daily_expected_effort * $workingDaysCount;
        }

        return round($expectedMonthlyHours, 2);
    }

    public function getBillableAmountForTerm(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        return round($this->getBillableHoursForMonth($monthToSubtract, $periodStartDate, $periodEndDate) * $this->client->billingDetails->service_rates, 2);
    }

    public function getTaxAmountForTerm($monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        // Todo: Implement tax calculation correctly as per the GST rules
        return round($this->getBillableAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) * ($this->client->country->initials == 'IN' ? config('invoice.tax-details.igst') : 0), 2);
    }

    public function getTotalPayableAmountForTerm(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        return $this->getBillableAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) + optional($this->client->billingDetails)->bank_charges;
    }

    public function getBillableHoursForMonth($monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $startDate = $periodStartDate ?: $this->client->getMonthStartDateAttribute($monthToSubtract);
        $endDate = $periodEndDate ?: $this->client->getMonthEndDateAttribute($monthToSubtract);
        return $this->getAllTeamMembers->sum(function ($teamMember) use ($startDate, $endDate) {
            if (! $teamMember->projectTeamMemberEffort) {
                return 0;
            }

            return $teamMember->projectTeamMemberEffort()
                ->where('added_on', '>=', $startDate)
                ->where('added_on', '<=', $endDate)
                ->sum('actual_effort');
        });
    }

    public function getResourceBillableAmount()
    {
        $service_rate = optional($this->billingDetail)->service_rates;
        if (! $service_rate) {
            $service_rate = $this->client->billingDetails->service_rates;
        }
        $totalAmount = 0;
        $numberOfMonths = 1;

        switch ($this->client->billingDetails->billing_frequency) {
            case 3:
                $numberOfMonths = 3;
                break;
            default:
                $numberOfMonths = 1;
        }

        foreach ($this->getTeamMembersGroupedByEngagement() as $groupedResources) {
            $totalAmount += ($groupedResources->billing_engagement / 100) * $groupedResources->resource_count * $service_rate * $numberOfMonths;
        }

        return round($totalAmount, 2);
    }

    public function meta()
    {
        return $this->hasMany(ProjectMeta::class);
    }

    public function getBillingLevelAttribute()
    {
        return optional($this->meta()->where('key', 'billing_level')->first())->value;
    }

    public function getLastUpdatedAtAttribute()
    {
        return optional($this->meta()->where('key', 'last_updated_at')->first())->value;
    }

    public function getNextInvoiceNumberAttribute()
    {
        $invoiceService = new InvoiceService();

        return $invoiceService->getInvoiceNumberPreview($this->client, $this, today(), config('project.meta_keys.billing_level.value.project.key'));
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeInvoiceReadyToSend($query)
    {
        $query->whereDoesntHave('invoices', function ($query) {
            return $query->whereMonth('sent_on', now(config('constants.timezone.indian')))->whereYear('sent_on', now(config('constants.timezone.indian')));
        })->whereHas('client.billingDetails', function ($query) {
            return $query->where('billing_date', '<=', today()->format('d'));
        });
    }

    public function ledgerAccounts()
    {
        return $this->hasMany(LedgerAccount::class);
    }

    public function ledgerAccountsOnlyCredit()
    {
        return $this->ledgerAccounts()->whereNotNull('credit');
    }

    public function ledgerAccountsOnlyDebit()
    {
        return $this->ledgerAccounts()->whereNotNull('debit');
    }

    public function getTotalLedgerAmount($quarter = null)
    {
        $amount = 0;
        $amount += (optional($this->ledgerAccountsOnlyCredit()->quarter($quarter))->get()->sum('credit') - optional($this->ledgerAccountsOnlyDebit()->quarter($quarter))->get()->sum('debit'));

        return $amount;
    }

    public function hasCustomInvoiceTemplate()
    {
        $template = config('invoice.templates.invoice.projects.' . $this->name);

        if ($template) {
            return true;
        }

        return false;
    }

    public function billingDetail()
    {
        return $this->hasOne(ProjectBillingDetail::class);
    }

    public function getstartDate()
    {
        $previousInvoice = invoice::where('project_id', $this->client_project_id)->orderby('sent_on', 'desc')->first();

        if ($previousInvoice) {
            $priviousbillingDate = $previousInvoice->sent_on;
        } else {
            $billingDate = DB::table('client_billing_details')->select('billing_date')->where('client_id', $this->client->id)->value('billing_date');
            $priviousbillingDate = $this->created_at->format('Y-m-d');

            return Carbon::createFromFormat('Y-m-d', $priviousbillingDate)->day($billingDate)->toDateString();
        }

        return $priviousbillingDate->format('Y-m-d');
    }

    public function nextBillingDate() // In this function nextbilling date comes from client level.
    {
        $clientFrequency = $this->client->billingDetails->billing_frequency;
        $previousInvoice = invoice::where('project_id', $this->client_project_id)->orderby('sent_on', 'desc')->first();

        if ($previousInvoice) {
            $priviousbillingDate = $previousInvoice->sent_on;
        } else {
            $priviousbillingDate = $this->created_at;
        }

        if ($clientFrequency == config('client.billing-frequency.quarterly.id')) { // clientFrequency = 3
            $nextBillingDate = $priviousbillingDate->addMonthsNoOverflow(3);

            return $nextBillingDate->subDay(2)->format('Y-m-d');
        } elseif ($clientFrequency == config('client.billing-frequency.monthly.id')) { // clientFrequency = 1
            $nextBillingDate = $priviousbillingDate->addMonthsNoOverflow(1);

            return $nextBillingDate->subDay(2)->format('Y-m-d');
        } elseif ($clientFrequency == config('client.billing-frequency.yearly.id')) { // clientFrequency = 4
            $nextBillingDate = $priviousbillingDate->addMonthsNoOverflow(12);

            return $nextBillingDate->subDay(2)->format('Y-m-d');
        }
        $nextBillingDate = $priviousbillingDate->addMonthsNoOverflow(1)->format('Y-m-d');

        return $nextBillingDate->subDay(2)->format('Y-m-d');
    }

    public function amcTotalProjectAmount(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $serviceRateTerm = $this->serviceRateTermFromProject_Billing_DetailsTable();
        $clientserviceRateTerm = $this->client->billingDetails->service_rate_term;

        if ($serviceRateTerm) {
            switch ($serviceRateTerm) {
                case 'per_hour':
                    $totalAmountInMonth = $this->getAmcTotalAmountPerHour();

                    return  $totalAmountInMonth;
                case 'per_month':
                    $totalAmountInMonth = $this->getAmcTotalAmountPerMonth();

                    return  $totalAmountInMonth;
                case 'per_quarter':
                    $totalAmountInQuater = $this->getAmcTotalAmountPerQuarterly();

                    return  $totalAmountInQuater;
                case 'per_year':
                    $totalAmountInYear = $this->serviceRateFromProjectBillingDetailsTable() + ($this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) + optional($this->client->billingDetails)->bank_charges);

                    return $totalAmountInYear;
            }
        }
        switch ($clientserviceRateTerm) {
            case 'per_hour':
                return $this->getAmcTotalAmountPerHourClientbase();
            case 'per_month':
                return $this->getAmcTotalAmountPerMonthClientbase();
            case 'per_quarter':
                return $this->getAmcTotalAmountPerQuarterClientbase();
            case 'per_year':
                return $this->client->billingDetails->service_rates
                + optional($this->client->billingDetails)->bank_charges
                + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);
        }
    }

    public function getAmcTotalAmountPerHourClientbase(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);
        $clientFrequency = $this->client->billingDetails->billing_frequency;
        $amount = ($this->client->billingDetails->service_rates * $this->amcBillableHours());

        if ($clientFrequency == 2) { // monthly
            return $amount + $taxandBankCharges;
        }
        if ($clientFrequency == 3) { // quarterly
            return ($amount * 3) + $taxandBankCharges;
        }
        if ($clientFrequency == 4) { // yearly
            return ($amount * 12) + $taxandBankCharges;
        }

        return $amount + $taxandBankCharges;
    }

    public function getAmcTotalAmountPerMonthClientbase(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);
        $clientFrequency = $this->client->billingDetails->billing_frequency;
        $amount = ($this->client->billingDetails->service_rates);

        if ($clientFrequency == 2) { // monthly
            return $amount + $taxandBankCharges;
        }
        if ($clientFrequency == 3) { // quarterly
            return ($amount * 3) + $taxandBankCharges;
        }
        if ($clientFrequency == 4) { // yearly
            return ($amount * 12) + $taxandBankCharges;
        }

        return $amount + $taxandBankCharges;
    }

    public function getAmcTotalAmountPerQuarterClientbase(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);
        $clientFrequency = $this->client->billingDetails->billing_frequency;
        $amount = ($this->client->billingDetails->service_rates);

        if ($clientFrequency == 3) { // quarterly
            return $amount + $taxandBankCharges;
        }
        if ($clientFrequency == 4) { // yearly
            return ($amount * 4) + $taxandBankCharges;
        }

        return $amount + $taxandBankCharges;
    }

    public function getAmcTotalAmountPerHour(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $startAndEndDate = $this->getTermStartAndEndDateForInvoice();
        $months = $startAndEndDate['startDate']->diffInMonths($startAndEndDate['endDate']);
        $amount = ($this->serviceRateFromProjectBillingDetailsTable() * $this->amcBillableHours());
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);

        if ($months == 0) { // monthly
            return $amount + $taxandBankCharges;
        }
        if ($months == 2) { // Quarterly
            return ($amount * 3) + $taxandBankCharges;
        }
        if ($months == 11) { // yearly
            return ($amount * 12) + $taxandBankCharges;
        }

        return $amount + $taxandBankCharges;
    }

    public function getAmcTotalAmountPerMonth(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $totalAmountInMonth = $this->serviceRateFromProjectBillingDetailsTable() + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) + optional($this->client->billingDetails)->bank_charges;
        $startAndEndDate = $this->getTermStartAndEndDateForInvoice();
        $months = $startAndEndDate['startDate']->diffInMonths($startAndEndDate['endDate']);
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);
        if ($months == 0) { // monthly
            return  $totalAmountInMonth + $taxandBankCharges;
        }
        if ($months == 2) { // Quarterly
            return  ($totalAmountInMonth * 3) + $taxandBankCharges;
        }
        if ($months == 11) { // yearly
            return  ($totalAmountInMonth * 12) + $taxandBankCharges;
        }

        return $totalAmountInMonth + $taxandBankCharges;
    }

    public function getAmcTotalAmountPerQuarterly(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $totalAmountInQuater = $this->serviceRateFromProjectBillingDetailsTable() + (3 * ($this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate) + optional($this->client->billingDetails)->bank_charges));
        $startAndEndDate = $this->getTermStartAndEndDateForInvoice();
        $months = $startAndEndDate['startDate']->diffInMonths($startAndEndDate['endDate']);
        $taxandBankCharges = optional($this->client->billingDetails)->bank_charges + $this->getTaxAmountForTerm($monthToSubtract, $periodStartDate, $periodEndDate);

        if ($months == 2) { // Quarterly
            return  $totalAmountInQuater + $taxandBankCharges;
        }
        if ($months == 11) { // yearly
            return  ($totalAmountInQuater * 4) + $taxandBankCharges;
        }

        return $totalAmountInQuater + $taxandBankCharges;
    }

    public function amcBillableHoursDisplay(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        $amcBillableHours = $this->getBillableHoursForMonth($monthToSubtract, $periodStartDate, $periodEndDate);
        $clientFrequency = $this->client->billingDetails->billing_frequency;

        if ($this->serviceRateTermFromProject_Billing_DetailsTable() == 'per_hour') {
            if ($clientFrequency == 2) {
                return $amcBillableHours;
            }
            if ($clientFrequency == 3) {
                return $amcBillableHours * 3;
            }
            if ($clientFrequency == 4) {
                return $amcBillableHours * 12;
            }
        }
    }

    public function amcBillableHours(int $monthToSubtract = 1, $periodStartDate = null, $periodEndDate = null)
    {
        return $this->getBillableHoursForMonth($monthToSubtract, $periodStartDate, $periodEndDate);
    }

    public function serviceRateFromProjectBillingDetailsTable()
    {
        $details = DB::table('project_billing_details')->where('project_id', $this->id)->first();
        $clientServiceRate = $this->client->billingDetails->service_rates;

        if (! empty($details) && $details->service_rates) {
            return $details->service_rates;
        }

        if (! empty($clientServiceRate)) {
            return $clientServiceRate;
        }

        return 0;
    }

    public function serviceRateTermFromProject_Billing_DetailsTable()
    {
        $details = DB::table('project_billing_details')->where('project_id', $this->id)->first();

        if ($details) {
            return $details->service_rate_term;
        } else {
            return  optional($this->client->billingDetails)->service_rate_term;
        }
    }

    public function getTermStartAndEndDateForInvoice()
    {
        $clientBillingDate = $this->client->billingDetails->billing_date;
        $lastInvoice = $this->lastSentDateInvoice();
        $last_marked_as_active_date = Carbon::createFromFormat('Y-m-d', $this->client->last_marked_as_active_date);
        $startDate = $this->startDateOfInvoice($lastInvoice, $clientBillingDate, $last_marked_as_active_date);
        $endDate = $this->endDateOfInvoice($clientBillingDate, $startDate);

        return [
            'startDate'=>$startDate,
            'endDate'=>$endDate
        ];
    }

    public function startDateOfInvoice($lastInvoice = null, $clientBillingDate = null, $last_marked_as_active_date = null)
    {
        $lastInvoiceSentDate = Carbon::createFromFormat('Y-m-d', $lastInvoice)->day($clientBillingDate);
        if ($last_marked_as_active_date->greaterThanOrEqualTo($lastInvoiceSentDate)) {
            return  $last_marked_as_active_date->day($clientBillingDate);
        }

        return $lastInvoiceSentDate;
    }

    public function lastSentDateInvoice()
    {
        $previousInvoice = invoice::where('project_id', $this->client_project_id)->orderby('sent_on', 'desc')->first();
        if ($previousInvoice) {
            $priviousbillingDate = $previousInvoice->sent_on;
        } else {
            $priviousbillingDate = $this->created_at;
        }

        return $priviousbillingDate->format('Y-m-d');
    }

    public function endDateOfInvoice($clientBillingDate = 0, $startDate = null)
    {
        $billingFrequency = $this->client->billingDetails->billing_frequency;
        $startdate = $startDate->copy();

        if ($billingFrequency == config('client.billing-frequency.quarterly.id')) { // clientFrequency = 3
            $endDate = $startdate->addMonthsNoOverflow(3)->startOfMonth()->day($clientBillingDate - 1);
        } elseif ($billingFrequency == config('client.billing-frequency.monthly.id')) { // clientFrequency = 1
            $endDate = $startdate->addMonthNoOverflow()->startOfMonth()->day($clientBillingDate - 1);
        } elseif ($billingFrequency == config('client.billing-frequency.yearly.id')) { // clientFrequency = 4
            $endDate = $startdate->addYearNoOverflow()->startOfMonth()->day($clientBillingDate - 1);
        } else {
            $endDate = $startdate->addMonthNoOverflow()->startOfMonth()->day($clientBillingDate - 1);
        }

        return $endDate;
    }
}
