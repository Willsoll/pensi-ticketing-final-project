<?php
// src/Controllers/CatalogController.php
// Public event catalog

namespace App\Controllers;

use App\Repositories\EventRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\TicketTierRepository;

class CatalogController extends BaseController
{
    private EventRepository $eventRepo;
    private CategoryRepository $categoryRepo;
    private TicketTierRepository $tierRepo;
    
    public function __construct()
    {
        parent::__construct();
        $this->eventRepo = new EventRepository($this->pdo);
        $this->categoryRepo = new CategoryRepository($this->pdo);
        $this->tierRepo = new TicketTierRepository($this->pdo);
    }
    
    public function index(): void
    {
        $events = $this->eventRepo->findActive();
        $categories = $this->categoryRepo->findAllOrdered();
        
        // Get ticket types for display
        $ticketTypes = [];
        foreach ($events as $event) {
            $tiers = $this->tierRepo->findAvailableForEvent($event['id_event']);
            if (!empty($tiers)) {
                $ticketTypes[$event['id_event']] = $tiers;
            }
        }
        
        // Get active vouchers
        $voucherRepo = new \App\Repositories\VoucherRepository($this->pdo);
        $vouchers = $voucherRepo->findActive();
        
        $this->view('catalog/index', [
            'events' => $events,
            'categories' => $categories,
            'ticketTypes' => $ticketTypes,
            'vouchers' => $vouchers,
            'pageTitle' => 'Katalog Acara Pensi',
        ]);
    }
    
    public function search(): void
    {
        $filters = [
            'category' => $this->input('category'),
            'city' => $this->input('city'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'search' => $this->input('search'),
        ];
        
        $events = $this->eventRepo->search(array_filter($filters));
        $categories = $this->categoryRepo->findAllOrdered();
        
        $this->view('catalog/search', [
            'events' => $events,
            'categories' => $categories,
            'filters' => $filters,
            'pageTitle' => 'Hasil Pencarian',
        ]);
    }
}