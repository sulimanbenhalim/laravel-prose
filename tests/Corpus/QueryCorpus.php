<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests\Corpus;

use Illuminate\Support\Facades\DB;
use SulimanBenhalim\Prose\Tests\AsylumSeeker;
use SulimanBenhalim\Prose\Tests\Course;
use SulimanBenhalim\Prose\Tests\Customer;
use SulimanBenhalim\Prose\Tests\Doctor;
use SulimanBenhalim\Prose\Tests\Employee;
use SulimanBenhalim\Prose\Tests\GymMember;
use SulimanBenhalim\Prose\Tests\GymSubscription;
use SulimanBenhalim\Prose\Tests\LegalAdvisor;
use SulimanBenhalim\Prose\Tests\LegalAppointment;
use SulimanBenhalim\Prose\Tests\MedicalAppointment;
use SulimanBenhalim\Prose\Tests\Order;
use SulimanBenhalim\Prose\Tests\Patient;
use SulimanBenhalim\Prose\Tests\PersonalTrainer;
use SulimanBenhalim\Prose\Tests\Product;
use SulimanBenhalim\Prose\Tests\ProductCategory;
use SulimanBenhalim\Prose\Tests\Project;
use SulimanBenhalim\Prose\Tests\Property;
use SulimanBenhalim\Prose\Tests\PropertyOffer;
use SulimanBenhalim\Prose\Tests\Student;
use SulimanBenhalim\Prose\Tests\Timesheet;
use SulimanBenhalim\Prose\Tests\Vehicle;
use SulimanBenhalim\Prose\Tests\VehicleBooking;
use SulimanBenhalim\Prose\Tests\WorkoutSession;

/**
 * Corpus of realistic Eloquent queries used to stress the prose generator.
 *
 * Each entry: name => [Closure(): string, must-contain[], must-not-contain[]].
 * The closures are re-evaluated under several frozen "now" anchors, so any
 * output that depends on the wall-clock time of day is flagged as a defect.
 */
class QueryCorpus
{
    /** @return array<string, array{0: \Closure, 1: string[], 2: string[]}> */
    public static function entries(): array
    {
        $groups = [
            self::basicOperators(),
            self::booleanFields(),
            self::likePatterns(),
            self::inLists(),
            self::nullChecks(),
            self::betweenRanges(),
            self::carbonRelative(),
            self::dateFunctions(),
            self::relationships(),
            self::multiColumn(),
            self::nestedGroups(),
            self::columnComparisons(),
            self::jsonQueries(),
            self::eagerLoads(),
            self::ordering(),
            self::limits(),
            self::aggregates(),
            self::updates(),
            self::deletes(),
            self::realWorldScenarios(),
            self::edgeCases(),
        ];

        $all = [];
        foreach ($groups as $group) {
            foreach ($group as $name => $entry) {
                $all[$name] = is_array($entry) ? $entry : [$entry, [], []];
            }
        }

        return $all;
    }

    private static function basicOperators(): array
    {
        $targets = [
            ['Product', Product::class, 'price_usd', [25, 99.99, 500]],
            ['Product', Product::class, 'stock_quantity_available', [0, 5, 100]],
            ['Customer', Customer::class, 'loyalty_points_balance', [0, 250, 10000]],
            ['Customer', Customer::class, 'total_lifetime_spending_usd', [100, 4999.5]],
            ['Order', Order::class, 'total_amount_usd', [50, 1500]],
            ['Order', Order::class, 'shipping_cost_usd', [0, 12.5]],
            ['Doctor', Doctor::class, 'years_of_practice', [5, 20]],
            ['Vehicle', Vehicle::class, 'mileage_km', [10000, 150000]],
            ['Vehicle', Vehicle::class, 'daily_rate_usd', [45, 200]],
            ['Student', Student::class, 'gpa_score', [2.0, 3.5]],
            ['Student', Student::class, 'credit_hours_completed', [30, 120]],
            ['Property', Property::class, 'bedroom_count', [2, 4]],
            ['Property', Property::class, 'listing_price_usd', [250000, 900000]],
            ['Employee', Employee::class, 'salary_usd', [60000, 150000]],
            ['Employee', Employee::class, 'vacation_days_remaining', [0, 15]],
        ];
        $stringTargets = [
            ['Order', Order::class, 'order_status', ['pending', 'shipped', 'cancelled']],
            ['Order', Order::class, 'payment_method', ['credit_card', 'paypal']],
            ['Employee', Employee::class, 'department', ['Engineering', 'Sales']],
            ['Doctor', Doctor::class, 'specialty', ['cardiology', 'pediatrics']],
            ['Vehicle', Vehicle::class, 'fuel_type', ['electric', 'diesel']],
            ['Property', Property::class, 'listing_status', ['active', 'sold']],
        ];

        $entries = [];
        foreach ($targets as [$label, $class, $field, $values]) {
            foreach (['=', '!=', '>', '>=', '<', '<='] as $op) {
                $value = $values[abs(crc32($op.$field)) % count($values)];
                $entries["basic/{$label}.{$field} {$op} {$value}"] = fn () => $class::where($field, $op, $value)->describe();
            }
        }
        foreach ($stringTargets as [$label, $class, $field, $values]) {
            foreach (['=', '!='] as $op) {
                foreach ($values as $value) {
                    $entries["basic/{$label}.{$field} {$op} {$value}"] = fn () => $class::where($field, $op, $value)->describe();
                }
            }
        }

        return $entries;
    }

    private static function booleanFields(): array
    {
        $fields = [
            [Customer::class, 'Customer', 'is_premium_member'],
            [Customer::class, 'Customer', 'email_notifications_enabled'],
            [Product::class, 'Product', 'is_currently_available'],
            [Product::class, 'Product', 'requires_shipping'],
            [Order::class, 'Order', 'is_gift_order'],
            [Order::class, 'Order', 'express_shipping_requested'],
            [GymMember::class, 'GymMember', 'has_medical_clearance'],
            [GymMember::class, 'GymMember', 'emergency_contact_on_file'],
            [Patient::class, 'Patient', 'has_chronic_conditions'],
            [Doctor::class, 'Doctor', 'is_accepting_new_patients'],
            [LegalAdvisor::class, 'LegalAdvisor', 'is_available_for_pro_bono'],
            [Vehicle::class, 'Vehicle', 'is_available_for_rent'],
            [Student::class, 'Student', 'is_international_student'],
            [Property::class, 'Property', 'pets_allowed'],
            [Property::class, 'Property', 'is_furnished'],
            [Employee::class, 'Employee', 'remote_work_eligible'],
            [Project::class, 'Project', 'is_confidential'],
            [Timesheet::class, 'Timesheet', 'is_approved'],
        ];

        $entries = [];
        foreach ($fields as [$class, $label, $field]) {
            foreach ([true, false] as $value) {
                $v = $value ? 'true' : 'false';
                $entries["bool/{$label}.{$field}={$v}"] = [
                    fn () => $class::where($field, $value)->describe(),
                    [], ['true', 'false'],
                ];
                $entries["bool/{$label}.{$field}!={$v}"] = [
                    fn () => $class::where($field, '!=', $value)->describe(),
                    [], ['true', 'false'],
                ];
            }
            $entries["bool/{$label}.{$field}=1-int"] = [
                fn () => $class::where($field, 1)->describe(), [], ['true', 'false', ' 1'],
            ];
        }

        return $entries;
    }

    private static function likePatterns(): array
    {
        $cases = [
            [Product::class, 'Product', 'product_name', 'laptop'],
            [Product::class, 'Product', 'product_description', 'wireless'],
            [Customer::class, 'Customer', 'email_address', '@gmail.com'],
            [Customer::class, 'Customer', 'full_name', 'John'],
            [Employee::class, 'Employee', 'job_title', 'Senior'],
            [Property::class, 'Property', 'city', 'Spring'],
            [Vehicle::class, 'Vehicle', 'make', 'Toy'],
            [Order::class, 'Order', 'order_number', 'ORD-2024'],
        ];

        $entries = [];
        foreach ($cases as [$class, $label, $field, $term]) {
            foreach ([
                ['contains', "%{$term}%", 'containing'],
                ['starts', "{$term}%", 'starting with'],
                ['ends', "%{$term}", 'ending with'],
                ['exact', $term, null],
            ] as [$kind, $pattern, $phrase]) {
                $must = $phrase ? [$phrase] : [];
                $entries["like/{$label}.{$field} {$kind}"] = [
                    fn () => $class::where($field, 'like', $pattern)->describe(), $must, ['%'],
                ];
                $entries["like/{$label}.{$field} not-{$kind}"] = [
                    fn () => $class::where($field, 'not like', $pattern)->describe(), [], ['%'],
                ];
            }
        }
        $entries['like/underscore-wildcard'] = [
            fn () => Product::where('sku_code', 'like', 'SKU_2024%')->describe(), [], [],
        ];

        return $entries;
    }

    private static function inLists(): array
    {
        $cases = [
            ['Order.status-3', fn () => Order::whereIn('order_status', ['pending', 'processing', 'shipped'])->describe()],
            ['Order.status-2', fn () => Order::whereIn('order_status', ['pending', 'processing'])->describe()],
            ['Order.status-1', fn () => Order::whereIn('order_status', ['pending'])->describe()],
            ['Order.payment-not-2', fn () => Order::whereNotIn('payment_method', ['crypto', 'wire_transfer'])->describe()],
            ['Customer.ids-numeric', fn () => Customer::whereIn('id', [1, 2, 3, 4, 5])->describe()],
            ['AsylumSeeker.countries', fn () => AsylumSeeker::whereIn('country_of_origin', ['Syria', 'Afghanistan', 'Eritrea'])->describe()],
            ['AsylumSeeker.languages', fn () => AsylumSeeker::whereIn('preferred_language', ['Arabic', 'Dari', 'Tigrinya', 'French', 'Ukrainian'])->describe()],
            ['Vehicle.fuel', fn () => Vehicle::whereIn('fuel_type', ['petrol', 'diesel', 'hybrid', 'electric'])->describe()],
            ['Student.majors-not', fn () => Student::whereNotIn('major_field', ['Undeclared'])->describe()],
            ['Employee.departments', fn () => Employee::whereIn('department', ['Engineering', 'Product', 'Design'])->describe()],
            ['Doctor.specialties-long', fn () => Doctor::whereIn('specialty', ['cardiology', 'neurology', 'oncology', 'pediatrics', 'radiology', 'surgery', 'urology', 'dermatology'])->describe()],
            ['Property.cities', fn () => Property::whereIn('city', ['Austin', 'Dallas'])->describe()],
            ['Project.status', fn () => Project::whereIn('status', ['active', 'on_hold'])->describe()],
            ['Product.skus-15', fn () => Product::whereIn('sku_code', array_map(fn ($i) => "SKU-{$i}", range(1, 15)))->describe()],
        ];

        $entries = [];
        foreach ($cases as [$name, $fn]) {
            $entries["in/{$name}"] = $fn;
        }

        return $entries;
    }

    private static function nullChecks(): array
    {
        $cases = [
            ['Customer.phone', fn () => Customer::whereNull('phone_number')->describe()],
            ['Customer.phone-not', fn () => Customer::whereNotNull('phone_number')->describe()],
            ['Customer.last_login', fn () => Customer::whereNull('last_login_at')->describe()],
            ['Customer.last_login-not', fn () => Customer::whereNotNull('last_login_at')->describe()],
            ['Customer.dob', fn () => Customer::whereNull('date_of_birth')->describe()],
            ['Order.instructions', fn () => Order::whereNull('special_instructions')->describe()],
            ['Order.delivery-date', fn () => Order::whereNull('estimated_delivery_date')->describe()],
            ['Patient.insurance', fn () => Patient::whereNull('insurance_provider')->describe()],
            ['Patient.insurance-not', fn () => Patient::whereNotNull('insurance_provider')->describe()],
            ['MedicalAppointment.diagnosis', fn () => MedicalAppointment::whereNull('diagnosis_notes')->describe()],
            ['WorkoutSession.checkout', fn () => WorkoutSession::whereNull('check_out_time')->describe()],
            ['WorkoutSession.trainer', fn () => WorkoutSession::whereNull('personal_trainer_id')->describe()],
            ['Student.graduation', fn () => Student::whereNull('graduation_date')->describe()],
            ['Student.dorm-not', fn () => Student::whereNotNull('dormitory_room_number')->describe()],
            ['Employee.manager', fn () => Employee::whereNull('manager_id')->describe()],
            ['Project.end-date', fn () => Project::whereNull('end_date')->describe()],
            ['Timesheet.approver', fn () => Timesheet::whereNull('approved_by')->describe()],
            ['AsylumSeeker.contact', fn () => AsylumSeeker::whereNull('emergency_contact_phone')->describe()],
            ['where-eq-null', fn () => Customer::where('phone_number', null)->describe()],
        ];

        $entries = [];
        foreach ($cases as [$name, $fn]) {
            $entries["null/{$name}"] = [$fn, [], ['null']];
        }

        return $entries;
    }

    private static function betweenRanges(): array
    {
        return [
            'between/Product.price' => fn () => Product::whereBetween('price_usd', [10, 50])->describe(),
            'between/Product.price-float' => fn () => Product::whereBetween('price_usd', [9.99, 49.99])->describe(),
            'between/Customer.spending' => fn () => Customer::whereBetween('total_lifetime_spending_usd', [500, 5000])->describe(),
            'between/Student.gpa' => fn () => Student::whereBetween('gpa_score', [3.0, 4.0])->describe(),
            'between/Property.bedrooms' => fn () => Property::whereBetween('bedroom_count', [2, 4])->describe(),
            'between/Employee.salary-not' => fn () => Employee::whereNotBetween('salary_usd', [40000, 200000])->describe(),
            'between/Order.amount-not' => fn () => Order::whereNotBetween('total_amount_usd', [0, 100])->describe(),
            'between/static-dates' => fn () => Order::whereBetween('created_at', ['2024-01-01', '2024-12-31'])->describe(),
            'between/static-dates-same-month' => fn () => Order::whereBetween('created_at', ['2024-06-01', '2024-06-30'])->describe(),
            'between/carbon-last-30-days' => fn () => Order::whereBetween('created_at', [now()->subDays(30), now()])->describe(),
            'between/carbon-last-week' => fn () => Order::whereBetween('created_at', [now()->subWeek(), now()])->describe(),
            'between/carbon-today' => fn () => WorkoutSession::whereBetween('check_in_time', [today(), today()->endOfDay()])->describe(),
            'between/carbon-this-month' => fn () => Order::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->describe(),
            'between/carbon-next-two-weeks' => fn () => MedicalAppointment::whereBetween('scheduled_datetime', [now(), now()->addWeeks(2)])->describe(),
            'between/vehicle-year' => fn () => Vehicle::whereBetween('manufacture_year', [2018, 2023])->describe(),
        ];
    }

    private static function carbonRelative(): array
    {
        $dateFields = [
            [Customer::class, 'Customer', 'last_login_at'],
            [Order::class, 'Order', 'created_at'],
            [MedicalAppointment::class, 'MedicalAppointment', 'scheduled_datetime'],
            [WorkoutSession::class, 'WorkoutSession', 'check_in_time'],
        ];
        $carbonOps = [
            'subMinutes5' => fn () => now()->subMinutes(5),
            'subMinutes30' => fn () => now()->subMinutes(30),
            'subHour' => fn () => now()->subHour(),
            'subHours6' => fn () => now()->subHours(6),
            'subHours12' => fn () => now()->subHours(12),
            'subDay' => fn () => now()->subDay(),
            'subDays3' => fn () => now()->subDays(3),
            'subDays7' => fn () => now()->subDays(7),
            'subDays14' => fn () => now()->subDays(14),
            'subDays30' => fn () => now()->subDays(30),
            'subDays45' => fn () => now()->subDays(45),
            'subDays90' => fn () => now()->subDays(90),
            'subWeeks2' => fn () => now()->subWeeks(2),
            'subWeeks6' => fn () => now()->subWeeks(6),
            'subMonth' => fn () => now()->subMonth(),
            'subMonths3' => fn () => now()->subMonths(3),
            'subMonths18' => fn () => now()->subMonths(18),
            'subYear' => fn () => now()->subYear(),
            'subYears2' => fn () => now()->subYears(2),
            'addMinutes45' => fn () => now()->addMinutes(45),
            'addHours2' => fn () => now()->addHours(2),
            'addDay' => fn () => now()->addDay(),
            'addDays3' => fn () => now()->addDays(3),
            'addDays14' => fn () => now()->addDays(14),
            'addWeek' => fn () => now()->addWeek(),
            'addMonths2' => fn () => now()->addMonths(2),
            'addYear' => fn () => now()->addYear(),
        ];

        $entries = [];
        foreach ($dateFields as [$class, $label, $field]) {
            foreach ($carbonOps as $opName => $valueFn) {
                foreach (['>', '<'] as $op) {
                    $entries["carbon/{$label}.{$field} {$op} {$opName}"] = fn () => $class::where($field, $op, $valueFn())->describe();
                }
            }
        }
        // >= and <= on a representative subset
        foreach (['subDays7', 'subMonth', 'addDays3'] as $opName) {
            $valueFn = $carbonOps[$opName];
            $entries["carbon/Order.created_at >= {$opName}"] = fn () => Order::where('created_at', '>=', $valueFn())->describe();
            $entries["carbon/Order.created_at <= {$opName}"] = fn () => Order::where('created_at', '<=', $valueFn())->describe();
        }
        $entries['carbon/eq-today-fn'] = fn () => Order::where('created_at', today())->describe();
        $entries['carbon/startOfDay'] = fn () => WorkoutSession::where('check_in_time', '>=', now()->startOfDay())->describe();
        $entries['carbon/endOfDay'] = fn () => WorkoutSession::where('check_in_time', '<=', now()->endOfDay())->describe();
        $entries['carbon/startOfWeek'] = fn () => Order::where('created_at', '>=', now()->startOfWeek())->describe();
        $entries['carbon/startOfMonth'] = fn () => Order::where('created_at', '>=', now()->startOfMonth())->describe();
        $entries['carbon/startOfYear'] = fn () => Order::where('created_at', '>=', now()->startOfYear())->describe();
        $entries['carbon/yesterday-fn'] = fn () => Order::where('created_at', '>=', now()->subDay()->startOfDay())->describe();

        return $entries;
    }

    private static function dateFunctions(): array
    {
        return [
            // whereDate compares calendar dates; phrasing must never claim clock-relative ranges
            'date/whereDate-eq-today' => [fn () => Order::whereDate('created_at', today())->describe(), ['today'], ['minute', 'hour']],
            'date/whereDate-gte-today' => [fn () => MedicalAppointment::whereDate('scheduled_datetime', '>=', today())->describe(), ['today'], ['yesterday', 'minute', 'hour', 'ago']],
            'date/whereDate-gt-today' => [fn () => MedicalAppointment::whereDate('scheduled_datetime', '>', today())->describe(), ['today'], ['yesterday', 'minute', 'hour']],
            'date/whereDate-lt-today' => [fn () => LegalAppointment::whereDate('scheduled_datetime', '<', today())->describe(), ['today'], ['yesterday', 'minute', 'hour']],
            'date/whereDate-lte-yesterday' => [fn () => Order::whereDate('created_at', '<=', today()->subDay())->describe(), ['yesterday'], ['minute', 'hour']],
            'date/whereDate-eq-yesterday' => [fn () => Order::whereDate('created_at', today()->subDay())->describe(), ['yesterday'], []],
            'date/whereDate-eq-tomorrow' => [fn () => MedicalAppointment::whereDate('scheduled_datetime', today()->addDay())->describe(), ['tomorrow'], []],
            'date/whereDate-static' => [fn () => Order::whereDate('created_at', '2024-06-15')->describe(), ['June 15, 2024'], []],
            'date/whereDate-static-gt' => [fn () => Order::whereDate('created_at', '>', '2024-01-01')->describe(), [], []],
            'date/whereDate-plus2days' => [fn () => Order::whereDate('estimated_delivery_date', '<=', now()->addDays(2))->describe(), [], ['tomorrow', 'yesterday']],
            'date/whereMonth-june' => [fn () => Student::whereMonth('date_of_birth', 6)->describe(), ['June'], []],
            'date/whereMonth-december' => [fn () => Order::whereMonth('created_at', 12)->describe(), ['December'], []],
            'date/whereMonth-gte' => [fn () => Order::whereMonth('created_at', '>=', 9)->describe(), [], []],
            'date/whereDay-15' => [fn () => GymSubscription::whereDay('next_billing_date', 15)->describe(), ['15'], []],
            'date/whereDay-1' => [fn () => GymSubscription::whereDay('billing_cycle_start_date', 1)->describe(), [], []],
            'date/whereYear-2024' => [fn () => Order::whereYear('created_at', 2024)->describe(), ['2024'], []],
            'date/whereYear-gt' => [fn () => Vehicle::whereYear('last_maintenance_date', '>', 2023)->describe(), ['2023'], []],
            'date/whereYear-current' => [fn () => Order::whereYear('created_at', now()->year)->describe(), [], []],
            'date/whereTime-morning' => [fn () => WorkoutSession::whereTime('check_in_time', '<', '09:00')->describe(), ['9:00 AM'], []],
            'date/whereTime-afternoon' => [fn () => MedicalAppointment::whereTime('scheduled_datetime', '>=', '14:30:00')->describe(), ['2:30 PM'], []],
            'date/whereTime-midnight' => [fn () => WorkoutSession::whereTime('check_in_time', '>', '00:00')->describe(), [], []],
            'date/static-date-string' => [fn () => Customer::where('created_at', '>', '2024-01-01')->describe(), ['January 1, 2024'], []],
            'date/static-datetime-string' => [fn () => Order::where('created_at', '>=', '2024-03-15 09:30:00')->describe(), ['March 15, 2024'], []],
            'date/birthdate-static' => [fn () => Patient::where('date_of_birth', '<', '1960-01-01')->describe(), ['1960'], []],
        ];
    }

    private static function relationships(): array
    {
        return [
            'rel/has-simple' => [fn () => Customer::whereHas('orders')->describe(), ['who have orders'], []],
            'rel/has-condition' => [fn () => Customer::whereHas('orders', fn ($q) => $q->where('total_amount_usd', '>', 1000))->describe(), ['who have orders'], []],
            'rel/has-two-conditions' => fn () => Customer::whereHas('orders', fn ($q) => $q->where('order_status', 'completed')->where('total_amount_usd', '>', 500))->describe(),
            'rel/doesnt-have' => [fn () => Customer::whereDoesntHave('orders')->describe(), ["don't have orders"], []],
            'rel/doesnt-have-condition' => fn () => Customer::whereDoesntHave('orders', fn ($q) => $q->where('order_status', 'cancelled'))->describe(),
            'rel/has-count-gte' => fn () => Customer::has('orders', '>=', 3)->describe(),
            'rel/has-count-eq' => fn () => Customer::has('orders', '=', 1)->describe(),
            'rel/doesnthave-shortcut' => fn () => Customer::doesntHave('orders')->describe(),
            'rel/whereRelation' => fn () => Customer::whereRelation('orders', 'total_amount_usd', '>', 100)->describe(),
            'rel/belongsTo-has' => fn () => Order::whereHas('customer', fn ($q) => $q->where('is_premium_member', true))->describe(),
            'rel/has-nested-date' => fn () => Customer::whereHas('orders', fn ($q) => $q->where('created_at', '>', now()->subDays(30)))->describe(),
            'rel/gym-sessions' => fn () => GymMember::whereHas('workoutSessions', fn ($q) => $q->where('duration_minutes', '>', 60))->describe(),
            'rel/gym-subscriptions' => fn () => GymMember::whereHas('gymSubscriptions', fn ($q) => $q->where('is_active_subscription', true))->describe(),
            'rel/patient-appointments' => fn () => Patient::whereHas('medicalAppointments', fn ($q) => $q->where('appointment_status', 'completed'))->describe(),
            'rel/doctor-appointments' => fn () => Doctor::whereHas('medicalAppointments', fn ($q) => $q->where('consultation_fee_usd', '>', 200))->describe(),
            'rel/asylum-appointments' => fn () => AsylumSeeker::whereHas('legalAppointments', fn ($q) => $q->where('interpreter_requested', true))->describe(),
            'rel/vehicle-bookings' => fn () => Vehicle::whereHas('bookings', fn ($q) => $q->where('booking_status', 'confirmed'))->describe(),
            'rel/vehicle-location' => fn () => Vehicle::whereHas('location', fn ($q) => $q->where('is_airport_location', true))->describe(),
            'rel/student-enrollments' => fn () => Student::whereHas('enrollments', fn ($q) => $q->where('final_grade', 'A'))->describe(),
            'rel/student-advisor' => fn () => Student::whereHas('advisor', fn ($q) => $q->where('is_department_head', true))->describe(),
            'rel/property-offers' => fn () => Property::whereHas('offers', fn ($q) => $q->where('offer_amount_usd', '>', 400000))->describe(),
            'rel/property-agent' => fn () => Property::whereHas('agent', fn ($q) => $q->where('is_certified_luxury_specialist', true))->describe(),
            'rel/employee-manager' => fn () => Employee::whereHas('manager', fn ($q) => $q->where('department', 'Engineering'))->describe(),
            'rel/employee-projects-pivot' => fn () => Employee::whereHas('projects', fn ($q) => $q->where('status', 'active'))->describe(),
            'rel/project-timesheets' => fn () => Project::whereHas('timesheets', fn ($q) => $q->where('is_approved', false))->describe(),
            'rel/category-products' => fn () => ProductCategory::whereHas('products', fn ($q) => $q->where('stock_quantity_available', '>', 0))->describe(),
            'rel/two-whereHas' => fn () => Customer::whereHas('orders', fn ($q) => $q->where('order_status', 'completed'))->whereHas('orders', fn ($q) => $q->where('is_gift_order', true))->describe(),
            'rel/has-like-inside' => fn () => Property::whereHas('viewings', fn ($q) => $q->where('client_email', 'like', '%@corp.com'))->describe(),
            'rel/has-in-inside' => fn () => Order::whereHas('orderItems', fn ($q) => $q->where('quantity_ordered', '>', 2))->describe(),
        ];
    }

    private static function multiColumn(): array
    {
        return [
            'multi/any-like' => [fn () => Product::whereAny(['product_name', 'product_description'], 'like', '%laptop%')->describe(), ['either'], []],
            'multi/any-three' => fn () => Customer::whereAny(['full_name', 'email_address', 'phone_number'], 'like', '%smith%')->describe(),
            'multi/any-eq' => fn () => Employee::whereAny(['department', 'job_title'], '=', 'Design')->describe(),
            'multi/all-like' => [fn () => Product::whereAll(['product_name', 'product_description'], 'like', '%pro%')->describe(), ['both'], []],
            'multi/none-like' => [fn () => Product::whereNone(['product_name', 'product_description'], 'like', '%refurbished%')->describe(), ['neither'], []],
            'multi/any-starts' => fn () => Patient::whereAny(['full_name', 'emergency_contact_name'], 'like', 'Dr.%')->describe(),
        ];
    }

    private static function nestedGroups(): array
    {
        return [
            'nested/or-group' => [
                fn () => Order::where('order_status', 'pending')->where(fn ($q) => $q->where('total_amount_usd', '>', 1000)->orWhere('is_gift_order', true))->describe(),
                [' or '], [],
            ],
            'nested/top-level-orWhere' => [
                fn () => Order::where('order_status', 'shipped')->orWhere('order_status', 'delivered')->describe(),
                [' or '], [],
            ],
            'nested/three-or' => [
                fn () => Vehicle::where('fuel_type', 'electric')->orWhere('fuel_type', 'hybrid')->orWhere('fuel_type', 'plugin')->describe(),
                [' or '], [],
            ],
            'nested/whereNot-value' => [
                fn () => Order::whereNot('order_status', 'cancelled')->describe(),
                [], [],
            ],
            'nested/whereNot-group' => [
                fn () => Customer::whereNot(fn ($q) => $q->where('is_premium_member', true)->where('loyalty_points_balance', '>', 1000))->describe(),
                [], [],
            ],
            'nested/and-of-ors' => fn () => Product::where(fn ($q) => $q->where('price_usd', '<', 20)->orWhere('price_usd', '>', 500))->where('is_currently_available', true)->describe(),
            'nested/deep-nesting' => fn () => Employee::where('employment_status', 'active')->where(fn ($q) => $q->where('department', 'Engineering')->orWhere(fn ($q2) => $q2->where('department', 'Product')->where('remote_work_eligible', true)))->describe(),
            'nested/orWhere-group-after' => fn () => Property::where('listing_status', 'active')->orWhere(fn ($q) => $q->where('listing_status', 'pending')->where('listing_price_usd', '<', 300000))->describe(),
        ];
    }

    private static function columnComparisons(): array
    {
        return [
            'col/price-vs-cost' => fn () => Product::whereColumn('price_usd', '<', 'wholesale_cost_usd')->describe(),
            'col/stock-vs-threshold' => fn () => Product::whereColumn('stock_quantity_available', '<=', 'minimum_stock_threshold')->describe(),
            'col/updated-vs-created' => fn () => Order::whereColumn('updated_at', '>', 'created_at')->describe(),
            'col/actual-vs-budget' => fn () => Project::whereColumn('actual_cost_usd', '>', 'budget_usd')->describe(),
            'col/hours-vs-estimate' => fn () => Project::whereColumn('billable_hours_actual', '>=', 'billable_hours_estimated')->describe(),
            'col/eq' => fn () => Timesheet::whereColumn('hours_worked', 'billable_hours')->describe(),
        ];
    }

    private static function jsonQueries(): array
    {
        return [
            'json/contains-string' => fn () => LegalAdvisor::whereJsonContains('specializations', 'asylum_law')->describe(),
            'json/contains-language' => fn () => LegalAdvisor::whereJsonContains('languages_spoken', 'Arabic')->describe(),
            'json/contains-tag' => fn () => Product::whereJsonContains('product_tags', 'featured')->describe(),
            'json/contains-goal' => fn () => GymMember::whereJsonContains('fitness_goals', 'weight_loss')->describe(),
            'json/contains-allergy' => fn () => Patient::whereJsonContains('known_allergies', 'penicillin')->describe(),
            'json/doesnt-contain' => fn () => Product::whereJsonDoesntContain('product_tags', 'discontinued')->describe(),
            'json/path-arrow' => fn () => Doctor::whereJsonContains('hospital_affiliations->primary', 'General Hospital')->describe(),
            'json/length' => fn () => LegalAdvisor::whereJsonLength('languages_spoken', '>', 2)->describe(),
        ];
    }

    private static function eagerLoads(): array
    {
        return [
            'with/single' => fn () => Order::with('customer')->describe(),
            'with/two' => fn () => Order::with('customer', 'orderItems')->describe(),
            'with/array' => fn () => Customer::with(['orders'])->describe(),
            'with/nested-dot' => fn () => Order::with('orderItems.product')->describe(),
            'with/nested-and-parent' => fn () => Order::with('orderItems', 'orderItems.product')->describe(),
            'with/constrained' => fn () => Customer::with(['orders' => fn ($q) => $q->where('order_status', 'completed')])->describe(),
            'with/columns-syntax' => fn () => Order::with('customer:id,full_name')->describe(),
            'with/three-levels' => fn () => Customer::with('orders.orderItems.product')->describe(),
            'with/plus-conditions' => fn () => Order::where('order_status', 'shipped')->with('customer', 'orderItems')->describe(),
            'with/gym' => fn () => GymMember::with('gymSubscriptions', 'workoutSessions')->describe(),
            'with/property' => fn () => Property::with('agent', 'viewings', 'offers')->describe(),
            'with/employee' => fn () => Employee::with('manager', 'timesheets')->describe(),
        ];
    }

    private static function ordering(): array
    {
        return [
            'order/price-desc' => [fn () => Product::orderBy('price_usd', 'desc')->describe(), ['highest to lowest'], []],
            'order/price-asc' => [fn () => Product::orderBy('price_usd')->describe(), ['lowest to highest'], []],
            'order/name-asc' => [fn () => Customer::orderBy('full_name')->describe(), ['A to Z'], []],
            'order/name-desc' => [fn () => Customer::orderBy('full_name', 'desc')->describe(), ['Z to A'], []],
            'order/date-desc' => [fn () => Order::orderBy('created_at', 'desc')->describe(), ['newest to oldest'], []],
            'order/date-asc' => [fn () => Order::orderBy('created_at')->describe(), ['oldest to newest'], []],
            'order/latest' => fn () => Order::latest()->describe(),
            'order/oldest' => fn () => Order::oldest()->describe(),
            'order/latest-column' => fn () => Customer::latest('last_login_at')->describe(),
            'order/orderByDesc' => fn () => Employee::orderByDesc('salary_usd')->describe(),
            'order/two-columns' => fn () => Employee::orderBy('department')->orderBy('salary_usd', 'desc')->describe(),
            'order/three-columns' => fn () => Student::orderBy('academic_year')->orderBy('gpa_score', 'desc')->orderBy('last_name')->describe(),
            'order/gpa' => fn () => Student::orderBy('gpa_score', 'desc')->describe(),
            'order/dob' => fn () => Patient::orderBy('date_of_birth')->describe(),
            'order/inRandomOrder' => fn () => Product::inRandomOrder()->describe(),
            'order/orderByRaw' => fn () => Product::orderByRaw('LENGTH(product_name) DESC')->describe(),
            'order/with-conditions' => fn () => Product::where('is_currently_available', true)->orderBy('price_usd', 'desc')->describe(),
        ];
    }

    private static function limits(): array
    {
        return [
            'limit/10' => [fn () => Product::limit(10)->describe(), ['first 10'], []],
            'limit/1' => fn () => Product::orderBy('price_usd', 'desc')->limit(1)->describe(),
            'limit/take5' => fn () => Customer::take(5)->describe(),
            'limit/take-skip' => fn () => Product::take(20)->skip(40)->describe(),
            'limit/offset-only' => fn () => Product::offset(10)->describe(),
            'limit/forPage' => fn () => Order::forPage(3, 25)->describe(),
            'limit/with-order' => fn () => Product::orderBy('price_usd')->limit(10)->describe(),
            'limit/complex' => fn () => Product::where('is_currently_available', true)->orderBy('price_usd', 'desc')->limit(20)->describe(),
        ];
    }

    private static function aggregates(): array
    {
        return [
            'agg/count-plain' => [fn () => Customer::query()->describeCount(), ['Count customers'], []],
            'agg/count-where' => fn () => Order::where('order_status', 'pending')->describeCount(),
            'agg/count-carbon' => fn () => Order::where('created_at', '>', now()->subWeeks(2))->describeCount(),
            'agg/sum-amount' => [fn () => Order::where('order_status', 'completed')->describeSum('total_amount_usd'), ['Sum'], []],
            'agg/sum-plain' => fn () => Order::query()->describeSum('tax_amount_usd'),
            'agg/avg' => [fn () => Customer::where('is_premium_member', true)->describeAvg('total_lifetime_spending_usd'), ['Average'], []],
            'agg/avg-gpa' => fn () => Student::where('academic_year', 'senior')->describeAvg('gpa_score'),
            'agg/max' => fn () => Property::where('listing_status', 'active')->describeMax('listing_price_usd'),
            'agg/min' => fn () => Product::where('is_currently_available', true)->describeMin('price_usd'),
            'agg/max-salary' => fn () => Employee::where('department', 'Engineering')->describeMax('salary_usd'),
            'agg/count-bool' => fn () => Vehicle::where('is_available_for_rent', true)->describeCount(),
            'agg/count-relationship' => fn () => Customer::whereHas('orders', fn ($q) => $q->where('total_amount_usd', '>', 500))->describeCount(),
            'agg/sum-hours' => fn () => Timesheet::where('is_approved', true)->describeSum('billable_hours'),
            'agg/avg-duration' => fn () => WorkoutSession::whereNotNull('check_out_time')->describeAvg('duration_minutes'),
            'agg/distinct' => fn () => Order::distinct()->describe(),
        ];
    }

    private static function updates(): array
    {
        return [
            'update/status-string' => [fn () => Order::where('order_status', 'pending')->describeUpdate(['order_status' => 'cancelled']), ['Update orders'], []],
            'update/bool-true' => fn () => Product::where('stock_quantity_available', '>', 0)->describeUpdate(['is_currently_available' => true]),
            'update/bool-false' => fn () => Product::where('stock_quantity_available', 0)->describeUpdate(['is_currently_available' => false]),
            'update/verify-email' => fn () => Customer::whereNull('email_verified_at')->describeUpdate(['email_verified_at' => now()]),
            'update/null-a-field' => fn () => Customer::where('email_notifications_enabled', false)->describeUpdate(['phone_number' => null]),
            'update/numeric' => fn () => Customer::where('is_premium_member', true)->describeUpdate(['loyalty_points_balance' => 0]),
            'update/decimal' => fn () => Product::where('category_id', 3)->describeUpdate(['price_usd' => 19.99]),
            'update/two-fields' => fn () => Order::where('order_status', 'processing')->describeUpdate(['order_status' => 'shipped', 'express_shipping_requested' => false]),
            'update/three-fields' => fn () => Employee::where('department', 'Sales')->describeUpdate(['employment_status' => 'active', 'remote_work_eligible' => true, 'vacation_days_remaining' => 20]),
            'update/string-with-spaces' => fn () => Property::where('listing_status', 'pending')->describeUpdate(['listing_status' => 'under contract']),
            'update/date-value' => fn () => GymSubscription::where('is_active_subscription', true)->describeUpdate(['next_billing_date' => now()->addMonth()]),
            'update/no-conditions' => fn () => Product::query()->describeUpdate(['requires_shipping' => true]),
            'update/empty-values' => fn () => Product::where('price_usd', '>', 100)->describeUpdate([]),
            'update/status-snake-value' => fn () => VehicleBooking::where('booking_status', 'pending_payment')->describeUpdate(['booking_status' => 'confirmed']),
            'update/carbon-condition' => fn () => Order::where('order_status', 'pending')->where('created_at', '<', now()->subHours(24))->describeUpdate(['order_status' => 'cancelled']),
        ];
    }

    private static function deletes(): array
    {
        return [
            'delete/status' => [fn () => Customer::where('email_notifications_enabled', false)->describeDelete(), ['Delete customers'], []],
            'delete/old-inactive' => fn () => Customer::where('last_login_at', '<', now()->subYears(2))->whereNull('email_verified_at')->describeDelete(),
            'delete/zero-stock' => fn () => Product::where('stock_quantity_available', 0)->describeDelete(),
            'delete/cancelled-orders' => fn () => Order::where('order_status', 'cancelled')->where('created_at', '<', now()->subMonths(6))->describeDelete(),
            'delete/no-conditions' => fn () => WorkoutSession::query()->describeDelete(),
            'delete/relationship' => fn () => Customer::whereDoesntHave('orders')->describeDelete(),
            'delete/in-list' => fn () => Order::whereIn('order_status', ['cancelled', 'refunded'])->describeDelete(),
            'delete/null-check' => fn () => WorkoutSession::whereNull('check_out_time')->where('check_in_time', '<', now()->subDay())->describeDelete(),
        ];
    }

    private static function realWorldScenarios(): array
    {
        return [
            'real/abandoned-carts' => fn () => Order::where('order_status', 'pending')->where('created_at', '<', now()->subHours(48))->whereNull('special_instructions')->describeCount(),
            'real/vip-outreach' => fn () => Customer::where('is_premium_member', true)->where('total_lifetime_spending_usd', '>', 10000)->where('last_login_at', '<', now()->subMonths(3))->orderBy('total_lifetime_spending_usd', 'desc')->limit(50)->describe(),
            'real/restock-report' => fn () => Product::whereColumn('stock_quantity_available', '<', 'minimum_stock_threshold')->where('is_currently_available', true)->with('category')->orderBy('stock_quantity_available')->describe(),
            'real/featured-bestsellers' => fn () => Product::where('is_currently_available', true)->where('stock_quantity_available', '>', 0)->whereHas('orderItems', fn ($q) => $q->where('quantity_ordered', '>', 1))->with('category')->orderBy('price_usd', 'desc')->limit(20)->describe(),
            'real/gym-renewals-due' => fn () => GymSubscription::where('is_active_subscription', true)->where('auto_renewal_enabled', false)->whereBetween('next_billing_date', [today(), today()->addDays(7)])->describe(),
            'real/gym-inactive-members' => fn () => GymMember::whereDoesntHave('workoutSessions', fn ($q) => $q->where('check_in_time', '>', now()->subDays(30)))->whereHas('gymSubscriptions', fn ($q) => $q->where('is_active_subscription', true))->describe(),
            'real/trainer-availability' => fn () => PersonalTrainer::where('is_currently_available', true)->where('years_of_experience', '>=', 3)->orderBy('hourly_rate_usd')->describe(),
            'real/no-show-followup' => fn () => MedicalAppointment::where('appointment_status', 'no_show')->whereDate('scheduled_datetime', '>=', today()->subDays(7))->with('patient', 'doctor')->describe(),
            'real/uninsured-patients' => fn () => Patient::whereNull('insurance_provider')->whereHas('medicalAppointments', fn ($q) => $q->where('consultation_fee_usd', '>', 100))->describe(),
            'real/interpreter-need' => fn () => AsylumSeeker::where('has_interpreter_required', true)->whereIn('preferred_language', ['Arabic', 'Dari', 'Pashto'])->whereHas('legalAppointments', fn ($q) => $q->where('appointment_status', 'scheduled'))->describe(),
            'real/minor-cases' => fn () => AsylumSeeker::where('is_minor_unaccompanied', true)->whereNull('emergency_contact_phone')->describe(),
            'real/probono-match' => fn () => LegalAdvisor::where('is_available_for_pro_bono', true)->whereJsonContains('languages_spoken', 'Arabic')->where('years_practicing_immigration_law', '>=', 5)->describe(),
            'real/fleet-maintenance' => fn () => Vehicle::where('is_available_for_rent', true)->where('next_inspection_due', '<', now()->addDays(14))->orderBy('next_inspection_due')->describe(),
            'real/overdue-returns' => fn () => VehicleBooking::where('booking_status', 'active')->where('booking_end_date', '<', now())->with('vehicle', 'customer')->describe(),
            'real/honor-roll' => fn () => Student::where('gpa_score', '>=', 3.7)->where('credit_hours_completed', '>=', 60)->whereNull('graduation_date')->orderBy('gpa_score', 'desc')->describe(),
            'real/at-risk-students' => fn () => Student::where('gpa_score', '<', 2.0)->whereHas('enrollments', fn ($q) => $q->where('attendance_percentage', '<', 70))->with('advisor')->describe(),
            'real/course-capacity' => fn () => Course::whereColumn('current_enrollment_count', '>=', 'max_enrollment_capacity')->describe(),
            'real/luxury-listings' => fn () => Property::where('listing_price_usd', '>', 1000000)->where('listing_status', 'active')->whereHas('agent', fn ($q) => $q->where('is_certified_luxury_specialist', true))->with('viewings')->orderBy('listing_price_usd', 'desc')->limit(10)->describe(),
            'real/stale-listings' => fn () => Property::where('listing_status', 'active')->where('listing_date', '<', now()->subMonths(6))->whereDoesntHave('offers')->describe(),
            'real/pending-offers' => fn () => PropertyOffer::where('offer_status', 'pending')->where('offer_expiry_date', '<=', today()->addDays(3))->describe(),
            'real/overtime-report' => fn () => Timesheet::where('overtime_hours', '>', 0)->where('is_approved', false)->whereBetween('work_date', [now()->startOfMonth(), now()->endOfMonth()])->describe(),
            'real/overbudget-projects' => fn () => Project::whereColumn('actual_cost_usd', '>', 'budget_usd')->where('status', 'active')->with('manager')->describe(),
            'real/review-due' => fn () => Employee::where('employment_status', 'active')->where('next_review_date', '<=', today()->addDays(30))->orderBy('next_review_date')->describe(),
            'real/idle-premium' => fn () => Customer::where('is_premium_member', true)->where('last_login_at', '<', now()->subDays(7))->whereNotNull('email_verified_at')->with('orders')->orderBy('total_lifetime_spending_usd', 'desc')->describe(),
            'real/gift-rush' => fn () => Order::where('is_gift_order', true)->where('express_shipping_requested', true)->whereIn('order_status', ['pending', 'processing'])->describeCount(),
            'real/refund-exposure' => fn () => Order::where('order_status', 'refund_requested')->describeSum('total_amount_usd'),
            'real/avg-consult-fee' => fn () => Doctor::where('is_accepting_new_patients', true)->describeAvg('consultation_fee_usd'),
            'real/cleanup-drafts' => fn () => Property::where('listing_status', 'draft')->where('updated_at', '<', now()->subMonths(3))->describeDelete(),
            'real/promote-members' => fn () => Customer::where('total_lifetime_spending_usd', '>', 5000)->where('is_premium_member', false)->describeUpdate(['is_premium_member' => true]),
            'real/close-stale-bookings' => fn () => VehicleBooking::where('booking_status', 'pending_payment')->where('created_at', '<', now()->subHours(2))->describeUpdate(['booking_status' => 'expired']),
        ];
    }

    private static function edgeCases(): array
    {
        return [
            'edge/no-conditions' => [fn () => Customer::query()->describe(), ['Find customers'], []],
            'edge/apostrophe-value' => fn () => Customer::where('full_name', "O'Brien")->describe(),
            'edge/unicode-value' => fn () => Customer::where('full_name', 'Müller')->describe(),
            'edge/empty-string' => fn () => Customer::where('phone_number', '')->describe(),
            'edge/negative-number' => fn () => Customer::where('loyalty_points_balance', '<', -100)->describe(),
            'edge/zero' => fn () => Product::where('weight_kg', 0)->describe(),
            'edge/float-precision' => fn () => Product::where('weight_kg', '>', 0.125)->describe(),
            'edge/large-number' => fn () => Order::where('total_amount_usd', '>', 1000000)->describe(),
            'edge/long-string' => fn () => Order::where('special_instructions', 'like', '%please leave the package behind the blue flower pot next to the garage door%')->describe(),
            'edge/numeric-string' => fn () => Order::where('order_number', '12345')->describe(),
            'edge/many-conditions' => fn () => Product::where('price_usd', '>', 10)->where('price_usd', '<', 100)->where('stock_quantity_available', '>', 0)->where('is_currently_available', true)->where('requires_shipping', true)->where('weight_kg', '<', 5)->where('category_id', 2)->where('sku_code', 'like', 'SKU%')->where('minimum_stock_threshold', '>=', 5)->where('wholesale_cost_usd', '<', 50)->where('product_name', 'like', '%a%')->where('product_description', 'not like', '%broken%')->where('id', '>', 0)->where('id', '<', 99999)->describe(),
            'edge/whereRaw' => fn () => Product::whereRaw('price_usd > wholesale_cost_usd * 2')->describe(),
            'edge/db-raw-value' => fn () => Product::where('price_usd', '>', DB::raw('wholesale_cost_usd'))->describe(),
            'edge/db-raw-column' => fn () => Product::where(DB::raw('LOWER(product_name)'), 'like', '%pro%')->describe(),
            'edge/groupBy-having' => fn () => Order::groupBy('customer_id')->having('total_amount_usd', '>', 100)->describe(),
            'edge/join' => fn () => Order::join('customers', 'orders.customer_id', '=', 'customers.id')->where('customers.is_premium_member', true)->describe(),
            'edge/select-columns' => fn () => Customer::select('id', 'full_name')->where('is_premium_member', true)->describe(),
            'edge/whereIntegerInRaw' => fn () => Customer::whereIntegerInRaw('id', [1, 2, 3])->describe(),
            'edge/whereBetweenColumns' => fn () => Timesheet::whereBetweenColumns('hours_worked', ['break_duration_minutes', 'billable_hours'])->describe(),
            'edge/whereLike-method' => fn () => Product::whereLike('product_name', '%deluxe%')->describe(),
            'edge/dotted-column' => fn () => Order::where('orders.order_status', 'pending')->describe(),
            'edge/whereExists-closure' => fn () => Customer::whereExists(fn ($q) => $q->select(DB::raw(1))->from('orders')->whereColumn('orders.customer_id', 'customers.id'))->describe(),
            'edge/when-true' => fn () => Product::when(true, fn ($q) => $q->where('price_usd', '>', 50))->describe(),
            'edge/unless' => fn () => Product::unless(false, fn ($q) => $q->where('is_currently_available', true))->describe(),
            'edge/tap-chain' => fn () => Customer::where('is_premium_member', true)->tap(fn ($q) => $q->where('loyalty_points_balance', '>', 0))->describe(),
            'edge/withCount' => fn () => Customer::withCount('orders')->where('is_premium_member', true)->describe(),
            'edge/value-with-percent-not-like' => fn () => Product::where('product_name', '50% off banner')->describe(),
            'edge/camelCase-model-name' => fn () => VehicleBooking::where('booking_status', 'confirmed')->describe(),
            'edge/three-word-model' => fn () => MedicalAppointment::where('appointment_type', 'follow_up')->describe(),
        ];
    }
}
