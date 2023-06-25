<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Constraint\Count;

class CountrySeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $usaStates = [
            "Ilocos Region" => 'Region I Ilocos Region',
            "CV" => 'Region II Cagayan Valley',
            "Central Luzon" => 'Region III Central Luzon',
            "Calabarzon" => 'Region IV-A CALABARZON',
            "MIMAROPA" => 'MIMAROPA Region',
            "Bicol Region" => 'Region V Bicol Region',
            "Western Visayas" => 'Region VI Western Visayas',
            "Central Visayas" => 'Region VII Central Visayas',
            "Eastern Visayas" => 'Region VIII Eastern Visayas',
            "Zamboanga Peninsula" => 'Region IX Zamboanga Peninsula',
            "Northern Mindanao" => 'Region X Northern Mindanao',
            "Davao Region" => 'Region XI Davao Region',
            "SOCCSKSARGEN" => 'Region XII SOCCSKSARGEN',
            "CARAGA" => 'Region XIII CARAGA',
            "NCR" => 'NCR National Capital Region',
            "Cordirella" => 'CAR Cordillera Adminisrative Region',
            "BARMM" => 'BARMM Bangsamoro Autonomous Region in Muslim Mindano',

        ];
        $countries = [
            ['code' => 'ph', 'name' => 'Philippines', 'states' => json_encode($usaStates)],
        ];
        Country::insert($countries);
    }
}
