<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'name'    => 'Alice Johnson',
                'email'   => 'alice.johnson@email.com',
                'phone'   => '555-0101',
                'address' => '12 Oak Street, Springfield, IL 62701',
            ],
            [
                'name'    => 'Bob Martinez',
                'email'   => 'bob.martinez@email.com',
                'phone'   => '555-0102',
                'address' => '45 Maple Avenue, Chicago, IL 60601',
            ],
            [
                'name'    => 'Carol White',
                'email'   => 'carol.white@email.com',
                'phone'   => '555-0103',
                'address' => '78 Pine Road, Rockford, IL 61101',
            ],
            [
                'name'    => 'David Brown',
                'email'   => 'david.brown@email.com',
                'phone'   => '555-0104',
                'address' => '23 Elm Drive, Peoria, IL 61602',
            ],
            [
                'name'    => 'Emma Davis',
                'email'   => 'emma.davis@email.com',
                'phone'   => '555-0105',
                'address' => '56 Cedar Lane, Aurora, IL 60505',
            ],
            [
                'name'    => 'Frank Wilson',
                'email'   => 'frank.wilson@email.com',
                'phone'   => '555-0106',
                'address' => '89 Birch Court, Naperville, IL 60540',
            ],
            [
                'name'    => 'Grace Taylor',
                'email'   => 'grace.taylor@email.com',
                'phone'   => '555-0107',
                'address' => '34 Walnut Street, Joliet, IL 60432',
            ],
            [
                'name'    => 'Henry Anderson',
                'email'   => 'henry.anderson@email.com',
                'phone'   => '555-0108',
                'address' => '67 Spruce Avenue, Waukegan, IL 60085',
            ],
            [
                'name'    => 'Isabella Thomas',
                'email'   => 'isabella.thomas@email.com',
                'phone'   => '555-0109',
                'address' => '11 Hickory Place, Elgin, IL 60120',
            ],
            [
                'name'    => 'James Jackson',
                'email'   => 'james.jackson@email.com',
                'phone'   => '555-0110',
                'address' => '44 Chestnut Blvd, Champaign, IL 61820',
            ],
            [
                'name'    => 'Karen Harris',
                'email'   => 'karen.harris@email.com',
                'phone'   => '555-0111',
                'address' => '77 Magnolia Way, Bloomington, IL 61701',
            ],
            [
                'name'    => 'Liam Clark',
                'email'   => 'liam.clark@email.com',
                'phone'   => '555-0112',
                'address' => '22 Poplar Street, Decatur, IL 62521',
            ],
            [
                'name'    => 'Mia Lewis',
                'email'   => 'mia.lewis@email.com',
                'phone'   => '555-0113',
                'address' => '55 Aspen Road, Evanston, IL 60201',
            ],
            [
                'name'    => 'Noah Robinson',
                'email'   => 'noah.robinson@email.com',
                'phone'   => '555-0114',
                'address' => '88 Sycamore Lane, Arlington Heights, IL 60004',
            ],
            [
                'name'    => 'Olivia Walker',
                'email'   => 'olivia.walker@email.com',
                'phone'   => '555-0115',
                'address' => '33 Cypress Court, Schaumburg, IL 60193',
            ],
            [
                'name'    => 'Paul Hall',
                'email'   => 'paul.hall@email.com',
                'phone'   => '555-0116',
                'address' => '66 Willow Drive, Palatine, IL 60067',
            ],
            [
                'name'    => 'Quinn Young',
                'email'   => 'quinn.young@email.com',
                'phone'   => '555-0117',
                'address' => '99 Dogwood Street, Orland Park, IL 60462',
            ],
            [
                'name'    => 'Rachel Allen',
                'email'   => 'rachel.allen@email.com',
                'phone'   => '555-0118',
                'address' => '18 Hawthorn Avenue, Tinley Park, IL 60477',
            ],
            [
                'name'    => 'Samuel Scott',
                'email'   => 'samuel.scott@email.com',
                'phone'   => '555-0119',
                'address' => '51 Redwood Place, Oak Park, IL 60301',
            ],
            [
                'name'    => 'Tara Green',
                'email'   => 'tara.green@email.com',
                'phone'   => '555-0120',
                'address' => '84 Juniper Blvd, Des Plaines, IL 60016',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(['email' => $customer['email']], $customer);
        }
    }
}
