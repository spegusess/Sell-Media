<?php

/**
 * Downloads Class
 *
 * @package Sell Media
 * @author Thad Allender <support@graphpaperpress.com>
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

Class SellMediaDownload {

    public function __construct(){
        add_action( 'init', array( &$this, 'download') , 100 );
    }


    /**
     * Set the file headers and force the download of a given file
     *
     * @return void
     */
    public function download(){

        if ( isset( $_GET['download'] ) && isset( $_GET['payment_id'] ) ) {

            $transaction_id = urldecode( $_GET['download'] );
            $payment_id     = urldecode( $_GET['payment_id'] );
            $product_id     = urldecode( $_GET['product_id'] );
            $attachment_id  = urldecode( $_GET['attachment_id'] );

            $verified = $this->verify( $transaction_id, $payment_id );

            if ( $verified ) {

                $requested_file = get_attached_file( $attachment_id );
                $file_type = wp_check_filetype( $requested_file );

                if ( ! ini_get( 'safe_mode' ) ){
                    set_time_limit( 0 );
                }

                if ( function_exists( 'get_magic_quotes_runtime' ) && get_magic_quotes_runtime() ) {
                    set_magic_quotes_runtime(0);
                }

                if ( function_exists( 'apache_setenv' ) ) @apache_setenv('no-gzip', 1);
                @ini_set( 'zlib.output_compression', 'Off' );

                nocache_headers();
                header( "Robots: none" );
                header( "Content-Type: " . $file_type['type'] . "" );
                header( "Content-Description: File Transfer" );
                header( "Content-Disposition: attachment; filename=\"" . basename( $requested_file ) . "\"" );
                header( "Content-Transfer-Encoding: binary" );

                // If this download is an image, generate the image sizes purchased and create a download
                if ( wp_attachment_is_image( $attachment_id ) ){
                    $this->download_image( $payment_id, $product_id, $attachment_id );
                // Otherwise, just deliver the download
                } else {
                    // Get the original uploaded file in the sell_media dir
                    $file_path = Sell_Media()->products->get_protected_file( $product_id, $attachment_id );
                    $this->download_package( $file_path );
                }
                do_action( 'sell_media_after_successful_download', $product_id );
                exit();
            } else {
                do_action( 'sell_media_before_failed_download', $product_id );
                wp_die( __( 'You do not have permission to download this file', 'sell_media'), __( 'Purchase Verification Failed', 'sell_media' ) );
            }
            exit;
        }

        // Rend purchase receipt?
        if ( isset( $_GET['resend_email'] ) && isset( $_GET['payment_id'] ) ){
            $payment_id = $_GET['payment_id'];
            $payment_email = get_meta_key( $payment_id, 'email' );

            Sell_Media()->payments->email_receipt( $payment_id, $payment_email );
        }
    }


    /**
     * Verifies a download purchase by checking if the post status is set to 'publish' for a
     * given purchase key;
     *
     * @param $download (string) The download hash
     * @return boolean
     */
    public function verify( $transaction_id=null, $payment_id=null ) {
        if ( $transaction_id == Sell_Media()->payments->get_meta_key( $payment_id, 'transaction_id' ) ){
            return true;
        }
    }


    /**
     * Downloads the correct size that was purchased.
     *
     * @param (int) $payment_id The payment ID for a purchase
     * @param (int) $product_id The product ID from a given payment
     */
    public function download_image( $payment_id=null, $product_id=null, $attachment_id=null ){
        // get height and width associated with the price group
        $price_group_id = Sell_Media()->payments->get_product_size( $payment_id, $product_id, 'download' );
        $width = sell_media_get_term_meta( $price_group_id, 'width', true );
        $height = sell_media_get_term_meta( $price_group_id, 'height', true );
        $file_download = $this->resize_image( $product_id, $attachment_id, $width, $height );

        return $file_download;
    }


    /**
     * Download helper for large files without changing PHP.INI
     * See https://github.com/EllisLab/CodeIgniter/wiki/Download-helper-for-large-files
     *
     * @access   public
     * @param    string  $file      The file
     * @param    boolean $retbytes  Return the bytes of file
     * @return   bool|string        If string, $status || $cnt
     */
    public function download_package( $file=null, $retbytes=true ) {

        $chunksize = 1024 * 1024;
        $buffer    = '';
        $cnt       = 0;
        $handle    = @fopen( $file, 'r' );

        if ( $size = @filesize( $file ) ) {
            header("Content-Length: " . $size );
        }

        if ( false === $handle ) {
            return false;
        }

        while ( ! @feof( $handle ) ) {
            $buffer = @fread( $handle, $chunksize );
            echo $buffer;

            if ( $retbytes ) {
                $cnt += strlen( $buffer );
            }
        }

        $status = @fclose( $handle );

        if ( $retbytes && $status ) {
            return $cnt;
        }

        return $status;
    }

    /**
     * Resize an image to the specified dimensions
     * http://codex.wordpress.org/Class_Reference/WP_Image_Editor
     *
     * Returns the new image file path
     *
     * @since 1.8.5
     * @param file path
     * @param width
     * @param width
     * @return resized image file path
     */
    public function resize_image( $product_id=null, $attachment_id=null, $width=null, $height=null ){
        $file_path = Sell_Media()->products->get_protected_file( $product_id, $attachment_id );
        $img = wp_get_image_editor( $file_path );
        if ( ! is_wp_error( $img ) ) {
            // resize if height and width supplied
            if ( $width || $height ) {
                if ( $width >= $height ) {
                    $max = $width;
                } else {
                    $max = $height;
                }
                $img->resize( $max, $max, false );
                $img->set_quality( 100 );
            }
            $img->stream();
        }
    }

}
new SellMediaDownload;