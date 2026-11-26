<?php

namespace BlueSheep\BusinessManager\Core;

use BlueSheep\BusinessManager\Admin\AdminMenu;
use BlueSheep\BusinessManager\Content\ContentTypes;
use BlueSheep\BusinessManager\Enquiry\EnquiryForm;
use BlueSheep\BusinessManager\Enquiry\EnquirySubmissionHandler;
use BlueSheep\BusinessManager\Database\MigrationRunner;
use BlueSheep\BusinessManager\Database\Migrations\CreateBusinessTables;
use BlueSheep\BusinessManager\Database\Migrations\CreateQuotationItineraryDays;
use BlueSheep\BusinessManager\Database\Migrations\AddNotificationDeduplicationIndex;
use BlueSheep\BusinessManager\Modules\Enquiries\EnquiryStatusHandler;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationPdfDownloadHandler;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationDispatchHandler;
use BlueSheep\BusinessManager\Modules\Quotations\QuotationAcceptanceHandler;
use BlueSheep\BusinessManager\Modules\Bookings\BookingConversionHandler;
use BlueSheep\BusinessManager\Modules\Bookings\BookingStatusHandler;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private ?MigrationRunner $migration_runner = null;

    public function init(): void
    {
        $content_types = new ContentTypes();
        $content_types->register();

        $enquiry_form = new EnquiryForm();
        $enquiry_form->register();

        $enquiry_handler = new EnquirySubmissionHandler();
        $enquiry_handler->register();

        $admin_menu = new AdminMenu();
        $admin_menu->register();

        $this->registerMigrations();

        (new EnquiryStatusHandler())->register();

        /*
         * Register quotation PDF download endpoint.
         */
        $pdf_download_handler = new QuotationPdfDownloadHandler();
        $pdf_download_handler->register();

        /*
         * Register manual quotation dispatch handler.
         */
        $dispatch_handler = new QuotationDispatchHandler();
        $dispatch_handler->register();

        /*
         * Register quotation acceptance handler.
         */
        $acceptance_handler = new QuotationAcceptanceHandler();
        $acceptance_handler->register();

        /*
         * Register booking conversion handler.
         */
        $booking_conversion_handler = new BookingConversionHandler();
        $booking_conversion_handler->register();

        /*
         * Register booking status handler.
         */
        $booking_status_handler = new BookingStatusHandler();
        $booking_status_handler->register();
    }

    private function registerMigrations(): void
    {
        $this->migration_runner = new MigrationRunner();

        $this->migration_runner->register(
            new CreateBusinessTables()
        );

        $this->migration_runner->register(
            new CreateQuotationItineraryDays()
        );

        $this->migration_runner->register(
            new AddNotificationDeduplicationIndex()
        );
    }
}
