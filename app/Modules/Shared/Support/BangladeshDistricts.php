<?php

namespace App\Modules\Shared\Support;

class BangladeshDistricts
{
    // All 64 districts, alphabetical — used by the checkout district
    // select and anywhere else that needs a canonical, consistent list
    // (a free-text field would fragment the same district into several
    // spellings, breaking any grouping/reporting built on top of it).
    public const ALL = [
        'Bagerhat', 'Bandarban', 'Barguna', 'Barisal', 'Bhola', 'Bogura',
        'Brahmanbaria', 'Chandpur', 'Chapainawabganj', 'Chattogram', 'Chuadanga',
        'Comilla', "Cox's Bazar", 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni',
        'Gaibandha', 'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore',
        'Jhalokati', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna',
        'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat',
        'Madaripur', 'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar',
        'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj',
        'Narsingdi', 'Natore', 'Netrokona', 'Nilphamari', 'Noakhali', 'Pabna',
        'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi',
        'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur', 'Sherpur',
        'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
    ];
}
