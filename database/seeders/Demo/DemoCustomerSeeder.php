<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\CustomerType;
use App\Models\Tenant\Store;
use App\Services\Tenant\Customer\CustomerGroupService;
use App\Services\Tenant\Customer\CustomerService;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    protected const CUSTOMERS = [
        ['name' => 'Wanjiru Kamau', 'phone' => '+254720100001', 'email' => 'wanjiru.kamau@example.test', 'type' => CustomerType::VIP, 'group' => 'VIP Customers', 'credit_limit' => 20000],
        ['name' => 'Otieno Ochieng', 'phone' => '+254720100002', 'email' => 'otieno.ochieng@example.test', 'type' => CustomerType::VIP, 'group' => 'VIP Customers', 'credit_limit' => 15000],
        ['name' => 'Akinyi Adhiambo', 'phone' => '+254720100003', 'email' => 'akinyi.adhiambo@example.test', 'type' => CustomerType::WHOLESALE, 'group' => 'Wholesale Buyers', 'credit_limit' => 50000],
        ['name' => 'Mutiso Kioko', 'phone' => '+254720100004', 'email' => 'mutiso.kioko@example.test', 'type' => CustomerType::WHOLESALE, 'group' => 'Wholesale Buyers', 'credit_limit' => 40000],
        ['name' => 'Chebet Kiplagat', 'phone' => '+254720100005', 'email' => 'chebet.kiplagat@example.test', 'type' => CustomerType::REGULAR, 'group' => 'Regular Customers', 'credit_limit' => 5000],
        ['name' => 'Njeri Maina', 'phone' => '+254720100006', 'email' => 'njeri.maina@example.test', 'type' => CustomerType::REGULAR, 'group' => 'Regular Customers', 'credit_limit' => 5000],
        ['name' => 'Kiprotich Rono', 'phone' => '+254720100007', 'email' => 'kiprotich.rono@example.test', 'type' => CustomerType::REGULAR, 'group' => 'Regular Customers'],
        ['name' => 'Mumbi Njoroge', 'phone' => '+254720100008', 'email' => 'mumbi.njoroge@example.test', 'type' => CustomerType::REGULAR, 'group' => 'Regular Customers'],
        ['name' => 'Barasa Wekesa', 'phone' => '+254720100009', 'email' => 'barasa.wekesa@example.test', 'type' => CustomerType::REGULAR],
        ['name' => 'Nafula Simiyu', 'phone' => '+254720100010', 'email' => 'nafula.simiyu@example.test', 'type' => CustomerType::REGULAR],
        ['name' => 'Cherono Kirui', 'phone' => '+254720100011', 'email' => null, 'type' => CustomerType::WALK_IN],
        ['name' => 'Omondi Owino', 'phone' => '+254720100012', 'email' => null, 'type' => CustomerType::WALK_IN],
        ['name' => 'Wambui Gitau', 'phone' => '+254720100013', 'email' => null, 'type' => CustomerType::WALK_IN],
        ['name' => 'Musyoka Mwangangi', 'phone' => '+254720100014', 'email' => null, 'type' => CustomerType::WALK_IN],
        ['name' => 'Achieng Odhiambo', 'phone' => '+254720100015', 'email' => 'achieng.odhiambo@example.test', 'type' => CustomerType::REGULAR],
        ['name' => 'Too Sang', 'phone' => '+254720100016', 'email' => 'too.sang@example.test', 'type' => CustomerType::REGULAR],
    ];

    public function run(CustomerService $customerService, CustomerGroupService $customerGroupService): void
    {
        $groups = collect([
            ['name' => 'VIP Customers', 'description' => 'Top-tier customers with the highest lifetime spend', 'discount_percentage' => 10, 'requires_approval' => true],
            ['name' => 'Wholesale Buyers', 'description' => 'Bulk-buying business customers', 'discount_percentage' => 15, 'requires_approval' => true],
            ['name' => 'Regular Customers', 'description' => 'Repeat customers on the standard loyalty tier', 'discount_percentage' => 5, 'requires_approval' => false],
        ])->map(fn (array $data) => $customerGroupService->createGroup($data + ['is_active' => true]))
            ->keyBy('name');

        $preferredStoreId = Store::mainStore()->firstOrFail()->id;

        foreach (self::CUSTOMERS as $spec) {
            $customer = $customerService->createCustomer([
                'name' => $spec['name'],
                'phone' => $spec['phone'],
                'email' => $spec['email'],
                'customer_type' => $spec['type'],
                'preferred_store_id' => $preferredStoreId,
                'credit_limit' => $spec['credit_limit'] ?? 0,
                'is_active' => true,
                'accepts_marketing' => $spec['email'] !== null,
                'registered_at' => now()->subDays(random_int(30, 300)),
            ]);

            if (isset($spec['group'])) {
                $customerGroupService->addMemberToGroup($groups[$spec['group']], $customer->id);
            }
        }

        $this->command->info('✓ Customers: '.count(self::CUSTOMERS).' customers across '.$groups->count().' groups');
    }
}
