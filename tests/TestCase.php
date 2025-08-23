<?php

declare(strict_types=1);

namespace SulimanBenhalim\Prose\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use SulimanBenhalim\Prose\ProseServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ProseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    private function setUpDatabase(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('asylum_seekers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('case_reference_number')->unique();
            $table->string('country_of_origin');
            $table->date('arrival_date');
            $table->string('preferred_language');
            $table->boolean('has_interpreter_required')->default(false);
            $table->boolean('is_minor_unaccompanied')->default(false);
            $table->string('emergency_contact_phone')->nullable();
            $table->text('special_needs_description')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('legal_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asylum_seeker_id')->constrained();
            $table->foreignId('legal_advisor_id')->constrained();
            $table->datetime('scheduled_datetime');
            $table->string('appointment_type');
            $table->string('appointment_status');
            $table->integer('duration_minutes')->default(60);
            $table->boolean('interpreter_requested')->default(false);
            $table->text('case_notes')->nullable();
            $table->decimal('consultation_fee_waived', 8, 2)->default(0);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('legal_advisors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('bar_registration_number')->unique();
            $table->json('languages_spoken');
            $table->json('specializations');
            $table->integer('years_practicing_immigration_law');
            $table->boolean('is_available_for_pro_bono')->default(true);
            $table->decimal('hourly_rate_usd', 8, 2);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('gym_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->unique();
            $table->string('full_name');
            $table->string('email_address')->unique();
            $table->string('phone_number');
            $table->date('date_of_birth');
            $table->date('membership_start_date');
            $table->boolean('has_medical_clearance')->default(false);
            $table->boolean('emergency_contact_on_file')->default(false);
            $table->json('fitness_goals')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('gym_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_member_id')->constrained();
            $table->string('subscription_type');
            $table->decimal('monthly_fee_usd', 8, 2);
            $table->date('billing_cycle_start_date');
            $table->date('next_billing_date');
            $table->boolean('is_active_subscription')->default(true);
            $table->boolean('auto_renewal_enabled')->default(true);
            $table->integer('months_remaining')->nullable();
            $table->json('included_services')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_member_id')->constrained();
            $table->foreignId('personal_trainer_id')->nullable()->constrained();
            $table->datetime('check_in_time');
            $table->datetime('check_out_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->json('equipment_used')->nullable();
            $table->text('workout_notes')->nullable();
            $table->integer('calories_burned_estimate')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('personal_trainers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('certification_type');
            $table->json('specialization_areas');
            $table->integer('years_of_experience');
            $table->decimal('hourly_rate_usd', 8, 2);
            $table->boolean('is_currently_available')->default(true);
            $table->integer('max_clients_per_day')->default(8);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id_number')->unique();
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('gender');
            $table->string('insurance_provider')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->boolean('has_chronic_conditions')->default(false);
            $table->json('known_allergies')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('medical_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('doctor_id')->constrained();
            $table->datetime('scheduled_datetime');
            $table->string('appointment_type');
            $table->string('appointment_status');
            $table->integer('estimated_duration_minutes');
            $table->decimal('consultation_fee_usd', 8, 2);
            $table->boolean('insurance_covers_visit')->default(false);
            $table->text('reason_for_visit');
            $table->text('diagnosis_notes')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('medical_license_number')->unique();
            $table->string('specialty');
            $table->integer('years_of_practice');
            $table->boolean('is_accepting_new_patients')->default(true);
            $table->json('hospital_affiliations')->nullable();
            $table->decimal('consultation_fee_usd', 8, 2);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('email_address')->unique();
            $table->string('full_name');
            $table->string('phone_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->datetime('last_login_at')->nullable();
            $table->boolean('email_notifications_enabled')->default(true);
            $table->boolean('is_premium_member')->default(false);
            $table->decimal('total_lifetime_spending_usd', 12, 2)->default(0);
            $table->integer('loyalty_points_balance')->default(0);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku_code')->unique();
            $table->string('product_name');
            $table->text('product_description');
            $table->foreignId('category_id')->constrained('product_categories');
            $table->decimal('price_usd', 10, 2);
            $table->decimal('wholesale_cost_usd', 10, 2);
            $table->integer('stock_quantity_available');
            $table->integer('minimum_stock_threshold')->default(10);
            $table->boolean('is_currently_available')->default(true);
            $table->boolean('requires_shipping')->default(true);
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->json('product_tags')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->string('category_slug')->unique();
            $table->text('category_description')->nullable();
            $table->boolean('is_featured_category')->default(false);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->decimal('total_amount_usd', 12, 2);
            $table->decimal('shipping_cost_usd', 8, 2)->default(0);
            $table->decimal('tax_amount_usd', 8, 2)->default(0);
            $table->string('order_status');
            $table->string('payment_method');
            $table->boolean('is_gift_order')->default(false);
            $table->boolean('express_shipping_requested')->default(false);
            $table->datetime('estimated_delivery_date')->nullable();
            $table->text('special_instructions')->nullable();
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity_ordered');
            $table->decimal('unit_price_usd', 10, 2);
            $table->decimal('total_line_amount_usd', 12, 2);
            $table->timestamps();
        });
    }
}

class AsylumSeeker extends Model
{
    protected $fillable = [
        'full_name', 'case_reference_number', 'country_of_origin', 'arrival_date',
        'preferred_language', 'has_interpreter_required', 'is_minor_unaccompanied',
        'emergency_contact_phone', 'special_needs_description',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'has_interpreter_required' => 'boolean',
        'is_minor_unaccompanied' => 'boolean',
    ];

    public function legalAppointments()
    {
        return $this->hasMany(LegalAppointment::class);
    }
}

class LegalAppointment extends Model
{
    protected $fillable = [
        'asylum_seeker_id', 'legal_advisor_id', 'scheduled_datetime', 'appointment_type',
        'appointment_status', 'duration_minutes', 'interpreter_requested', 'case_notes', 'consultation_fee_waived',
    ];

    protected $casts = [
        'scheduled_datetime' => 'datetime',
        'interpreter_requested' => 'boolean',
        'consultation_fee_waived' => 'decimal:2',
    ];

    public function asylumSeeker()
    {
        return $this->belongsTo(AsylumSeeker::class);
    }

    public function legalAdvisor()
    {
        return $this->belongsTo(LegalAdvisor::class);
    }
}

class LegalAdvisor extends Model
{
    protected $fillable = [
        'full_name', 'bar_registration_number', 'languages_spoken', 'specializations',
        'years_practicing_immigration_law', 'is_available_for_pro_bono', 'hourly_rate_usd',
    ];

    protected $casts = [
        'languages_spoken' => 'array',
        'specializations' => 'array',
        'is_available_for_pro_bono' => 'boolean',
        'hourly_rate_usd' => 'decimal:2',
    ];

    public function legalAppointments()
    {
        return $this->hasMany(LegalAppointment::class);
    }
}

class GymMember extends Model
{
    protected $fillable = [
        'member_number', 'full_name', 'email_address', 'phone_number', 'date_of_birth',
        'membership_start_date', 'has_medical_clearance', 'emergency_contact_on_file', 'fitness_goals',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'membership_start_date' => 'date',
        'has_medical_clearance' => 'boolean',
        'emergency_contact_on_file' => 'boolean',
        'fitness_goals' => 'array',
    ];

    public function gymSubscriptions()
    {
        return $this->hasMany(GymSubscription::class);
    }

    public function workoutSessions()
    {
        return $this->hasMany(WorkoutSession::class);
    }
}

class GymSubscription extends Model
{
    protected $fillable = [
        'gym_member_id', 'subscription_type', 'monthly_fee_usd', 'billing_cycle_start_date',
        'next_billing_date', 'is_active_subscription', 'auto_renewal_enabled', 'months_remaining', 'included_services',
    ];

    protected $casts = [
        'monthly_fee_usd' => 'decimal:2',
        'billing_cycle_start_date' => 'date',
        'next_billing_date' => 'date',
        'is_active_subscription' => 'boolean',
        'auto_renewal_enabled' => 'boolean',
        'included_services' => 'array',
    ];

    public function gymMember()
    {
        return $this->belongsTo(GymMember::class);
    }
}

class WorkoutSession extends Model
{
    protected $fillable = [
        'gym_member_id', 'personal_trainer_id', 'check_in_time', 'check_out_time',
        'duration_minutes', 'equipment_used', 'workout_notes', 'calories_burned_estimate',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'equipment_used' => 'array',
    ];

    public function gymMember()
    {
        return $this->belongsTo(GymMember::class);
    }

    public function personalTrainer()
    {
        return $this->belongsTo(PersonalTrainer::class);
    }
}

class PersonalTrainer extends Model
{
    protected $fillable = [
        'full_name', 'certification_type', 'specialization_areas', 'years_of_experience',
        'hourly_rate_usd', 'is_currently_available', 'max_clients_per_day',
    ];

    protected $casts = [
        'specialization_areas' => 'array',
        'hourly_rate_usd' => 'decimal:2',
        'is_currently_available' => 'boolean',
    ];

    public function workoutSessions()
    {
        return $this->hasMany(WorkoutSession::class);
    }
}

class Patient extends Model
{
    protected $fillable = [
        'patient_id_number', 'full_name', 'date_of_birth', 'gender', 'insurance_provider',
        'insurance_policy_number', 'has_chronic_conditions', 'known_allergies',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'has_chronic_conditions' => 'boolean',
        'known_allergies' => 'array',
    ];

    public function medicalAppointments()
    {
        return $this->hasMany(MedicalAppointment::class);
    }
}

class MedicalAppointment extends Model
{
    protected $fillable = [
        'patient_id', 'doctor_id', 'scheduled_datetime', 'appointment_type', 'appointment_status',
        'estimated_duration_minutes', 'consultation_fee_usd', 'insurance_covers_visit',
        'reason_for_visit', 'diagnosis_notes',
    ];

    protected $casts = [
        'scheduled_datetime' => 'datetime',
        'consultation_fee_usd' => 'decimal:2',
        'insurance_covers_visit' => 'boolean',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}

class Doctor extends Model
{
    protected $fillable = [
        'full_name', 'medical_license_number', 'specialty', 'years_of_practice',
        'is_accepting_new_patients', 'hospital_affiliations', 'consultation_fee_usd',
    ];

    protected $casts = [
        'is_accepting_new_patients' => 'boolean',
        'hospital_affiliations' => 'array',
        'consultation_fee_usd' => 'decimal:2',
    ];

    public function medicalAppointments()
    {
        return $this->hasMany(MedicalAppointment::class);
    }
}

class Customer extends Model
{
    protected $fillable = [
        'email_address', 'full_name', 'phone_number', 'date_of_birth', 'last_login_at',
        'email_notifications_enabled', 'is_premium_member', 'total_lifetime_spending_usd', 'loyalty_points_balance',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'last_login_at' => 'datetime',
        'email_notifications_enabled' => 'boolean',
        'is_premium_member' => 'boolean',
        'total_lifetime_spending_usd' => 'decimal:2',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

class Product extends Model
{
    protected $fillable = [
        'sku_code', 'product_name', 'product_description', 'category_id', 'price_usd',
        'wholesale_cost_usd', 'stock_quantity_available', 'minimum_stock_threshold',
        'is_currently_available', 'requires_shipping', 'weight_kg', 'product_tags',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
        'wholesale_cost_usd' => 'decimal:2',
        'is_currently_available' => 'boolean',
        'requires_shipping' => 'boolean',
        'weight_kg' => 'decimal:3',
        'product_tags' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}

class ProductCategory extends Model
{
    protected $fillable = [
        'category_name', 'category_slug', 'category_description',
        'is_featured_category', 'display_order',
    ];

    protected $casts = [
        'is_featured_category' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}

class Order extends Model
{
    protected $fillable = [
        'order_number', 'customer_id', 'total_amount_usd', 'shipping_cost_usd', 'tax_amount_usd',
        'order_status', 'payment_method', 'is_gift_order', 'express_shipping_requested',
        'estimated_delivery_date', 'special_instructions',
    ];

    protected $casts = [
        'total_amount_usd' => 'decimal:2',
        'shipping_cost_usd' => 'decimal:2',
        'tax_amount_usd' => 'decimal:2',
        'is_gift_order' => 'boolean',
        'express_shipping_requested' => 'boolean',
        'estimated_delivery_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'quantity_ordered', 'unit_price_usd', 'total_line_amount_usd',
    ];

    protected $casts = [
        'unit_price_usd' => 'decimal:2',
        'total_line_amount_usd' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

class Vehicle extends Model
{
    protected $fillable = [
        'vin_number', 'license_plate', 'make', 'model_name', 'manufacture_year', 'mileage_km',
        'fuel_type', 'transmission_type', 'color', 'is_available_for_rent', 'daily_rate_usd',
        'last_maintenance_date', 'next_inspection_due', 'insurance_expiry_date', 'location_id',
    ];

    protected $casts = [
        'manufacture_year' => 'integer',
        'mileage_km' => 'integer',
        'is_available_for_rent' => 'boolean',
        'daily_rate_usd' => 'decimal:2',
        'last_maintenance_date' => 'date',
        'next_inspection_due' => 'date',
        'insurance_expiry_date' => 'date',
    ];

    public function bookings()
    {
        return $this->hasMany(VehicleBooking::class);
    }

    public function location()
    {
        return $this->belongsTo(RentalLocation::class);
    }
}

class VehicleBooking extends Model
{
    protected $fillable = [
        'vehicle_id', 'customer_id', 'booking_start_date', 'booking_end_date',
        'pickup_location_id', 'dropoff_location_id', 'total_cost_usd', 'payment_status',
        'booking_status', 'driver_license_verified', 'additional_driver_count',
    ];

    protected $casts = [
        'booking_start_date' => 'datetime',
        'booking_end_date' => 'datetime',
        'total_cost_usd' => 'decimal:2',
        'driver_license_verified' => 'boolean',
        'additional_driver_count' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

class RentalLocation extends Model
{
    protected $fillable = [
        'location_name', 'street_address', 'city', 'postal_code', 'country',
        'phone_number', 'operating_hours', 'is_airport_location', 'parking_capacity',
    ];

    protected $casts = [
        'is_airport_location' => 'boolean',
        'parking_capacity' => 'integer',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'location_id');
    }
}

class Student extends Model
{
    protected $fillable = [
        'student_id', 'first_name', 'last_name', 'email_address', 'date_of_birth',
        'enrollment_date', 'graduation_date', 'academic_year', 'major_field', 'minor_field',
        'gpa_score', 'credit_hours_completed', 'is_international_student', 'scholarship_amount_usd',
        'advisor_id', 'dormitory_room_number',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'enrollment_date' => 'date',
        'graduation_date' => 'date',
        'gpa_score' => 'decimal:2',
        'credit_hours_completed' => 'integer',
        'is_international_student' => 'boolean',
        'scholarship_amount_usd' => 'decimal:2',
    ];

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function advisor()
    {
        return $this->belongsTo(Faculty::class, 'advisor_id');
    }
}

class Course extends Model
{
    protected $fillable = [
        'course_code', 'course_title', 'description', 'credit_hours', 'semester',
        'academic_year', 'max_enrollment_capacity', 'current_enrollment_count',
        'instructor_id', 'classroom_location', 'schedule_days', 'start_time', 'end_time',
    ];

    protected $casts = [
        'credit_hours' => 'integer',
        'max_enrollment_capacity' => 'integer',
        'current_enrollment_count' => 'integer',
        'start_time' => 'time',
        'end_time' => 'time',
    ];

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Faculty::class, 'instructor_id');
    }
}

class CourseEnrollment extends Model
{
    protected $fillable = [
        'student_id', 'course_id', 'enrollment_date', 'final_grade', 'attendance_percentage',
        'is_audit_only', 'withdrawal_date', 'late_enrollment_fee_usd',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'attendance_percentage' => 'decimal:1',
        'is_audit_only' => 'boolean',
        'withdrawal_date' => 'date',
        'late_enrollment_fee_usd' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}

class Faculty extends Model
{
    protected $fillable = [
        'employee_id', 'title', 'first_name', 'last_name', 'email_address', 'phone_number',
        'department', 'hire_date', 'tenure_status', 'salary_usd', 'office_location',
        'research_interests', 'is_department_head', 'max_advisee_count',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary_usd' => 'decimal:2',
        'is_department_head' => 'boolean',
        'max_advisee_count' => 'integer',
    ];

    public function advisees()
    {
        return $this->hasMany(Student::class, 'advisor_id');
    }

    public function courses()
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }
}

class Property extends Model
{
    protected $fillable = [
        'property_id', 'property_type', 'street_address', 'city', 'state_province', 'postal_code',
        'square_footage', 'bedroom_count', 'bathroom_count', 'parking_spaces', 'lot_size_sqft',
        'year_built', 'listing_price_usd', 'monthly_rent_usd', 'property_tax_annual_usd',
        'hoa_fees_monthly_usd', 'is_furnished', 'pets_allowed', 'utilities_included',
        'listing_status', 'listing_date', 'last_price_change_date', 'agent_id',
    ];

    protected $casts = [
        'square_footage' => 'integer',
        'bedroom_count' => 'integer',
        'bathroom_count' => 'integer',
        'parking_spaces' => 'integer',
        'lot_size_sqft' => 'integer',
        'year_built' => 'integer',
        'listing_price_usd' => 'decimal:2',
        'monthly_rent_usd' => 'decimal:2',
        'property_tax_annual_usd' => 'decimal:2',
        'hoa_fees_monthly_usd' => 'decimal:2',
        'is_furnished' => 'boolean',
        'pets_allowed' => 'boolean',
        'utilities_included' => 'boolean',
        'listing_date' => 'date',
        'last_price_change_date' => 'date',
    ];

    public function agent()
    {
        return $this->belongsTo(RealEstateAgent::class, 'agent_id');
    }

    public function viewings()
    {
        return $this->hasMany(PropertyViewing::class);
    }

    public function offers()
    {
        return $this->hasMany(PropertyOffer::class);
    }
}

class RealEstateAgent extends Model
{
    protected $fillable = [
        'license_number', 'first_name', 'last_name', 'email_address', 'phone_number',
        'brokerage_name', 'years_experience', 'specialization_areas', 'commission_rate_percentage',
        'total_sales_volume_usd', 'active_listings_count', 'is_certified_luxury_specialist',
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'commission_rate_percentage' => 'decimal:2',
        'total_sales_volume_usd' => 'decimal:2',
        'active_listings_count' => 'integer',
        'is_certified_luxury_specialist' => 'boolean',
    ];

    public function properties()
    {
        return $this->hasMany(Property::class, 'agent_id');
    }
}

class PropertyViewing extends Model
{
    protected $fillable = [
        'property_id', 'client_name', 'client_email', 'client_phone', 'viewing_date',
        'viewing_time', 'duration_minutes', 'feedback_rating', 'interest_level',
        'follow_up_required', 'notes',
    ];

    protected $casts = [
        'viewing_date' => 'date',
        'viewing_time' => 'time',
        'duration_minutes' => 'integer',
        'feedback_rating' => 'integer',
        'follow_up_required' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}

class PropertyOffer extends Model
{
    protected $fillable = [
        'property_id', 'buyer_name', 'buyer_email', 'offer_amount_usd', 'earnest_money_usd',
        'financing_type', 'closing_date', 'inspection_contingency', 'appraisal_contingency',
        'offer_status', 'counter_offer_amount_usd', 'offer_submitted_date', 'offer_expiry_date',
    ];

    protected $casts = [
        'offer_amount_usd' => 'decimal:2',
        'earnest_money_usd' => 'decimal:2',
        'closing_date' => 'date',
        'inspection_contingency' => 'boolean',
        'appraisal_contingency' => 'boolean',
        'counter_offer_amount_usd' => 'decimal:2',
        'offer_submitted_date' => 'date',
        'offer_expiry_date' => 'date',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}

class Employee extends Model
{
    protected $fillable = [
        'employee_id', 'first_name', 'last_name', 'email', 'phone_number', 'hire_date',
        'department', 'job_title', 'salary_usd', 'employment_status', 'manager_id',
        'work_location', 'remote_work_eligible', 'vacation_days_remaining', 'sick_days_used',
        'performance_rating', 'next_review_date', 'security_clearance_level',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary_usd' => 'decimal:2',
        'remote_work_eligible' => 'boolean',
        'vacation_days_remaining' => 'integer',
        'sick_days_used' => 'integer',
        'performance_rating' => 'decimal:1',
        'next_review_date' => 'date',
    ];

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_assignments');
    }
}

class Project extends Model
{
    protected $fillable = [
        'project_code', 'project_name', 'description', 'start_date', 'end_date',
        'budget_usd', 'actual_cost_usd', 'project_manager_id', 'client_id', 'priority_level',
        'completion_percentage', 'status', 'billable_hours_estimated', 'billable_hours_actual',
        'requires_security_clearance', 'is_confidential',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget_usd' => 'decimal:2',
        'actual_cost_usd' => 'decimal:2',
        'completion_percentage' => 'integer',
        'billable_hours_estimated' => 'decimal:1',
        'billable_hours_actual' => 'decimal:1',
        'requires_security_clearance' => 'boolean',
        'is_confidential' => 'boolean',
    ];

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'project_assignments');
    }

    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }
}

class Timesheet extends Model
{
    protected $fillable = [
        'employee_id', 'project_id', 'work_date', 'start_time', 'end_time',
        'break_duration_minutes', 'hours_worked', 'overtime_hours', 'billable_hours',
        'task_description', 'is_approved', 'approved_by', 'approval_date',
    ];

    protected $casts = [
        'work_date' => 'date',
        'start_time' => 'time',
        'end_time' => 'time',
        'break_duration_minutes' => 'integer',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'billable_hours' => 'decimal:2',
        'is_approved' => 'boolean',
        'approval_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }
}
