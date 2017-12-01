<?php
function cxp_get_email_message( $post, $user ) {
	
	$text = cxp_email_merge_tags($post, $user);
    $ids = array('post'=>$post->ID, 'user'=>$user->ID);
    $pattern = '/href\="'.preg_quote($post->site_url, '/').'(.*?)"/';
    $text['content'] = preg_replace_callback( $pattern,
    function($match) use ($ids) {
        $link = preg_replace( '/href\="(.*?)"/', '$1', $match[0] );
        return 'href="'.esc_url( add_query_arg( array('cxp_email_link_id'=>$ids['post'], 'cxp_email_link_user_id'=>$ids['user']), $link ) ).'"';
    }, $text['content'] );
	
    $html_message = '<!doctype html>
    <html>

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>'.$text['title'].'</title>

        <style type="text/css">

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                height: 100% !important;
                width: 100% !important;
            }
            img {
                width: auto;
                height: auto;
                max-width: 100%;
            }
            * {
                -ms-text-size-adjust: 100%;
                -webkit-text-size-adjust: 100%;
            }
            .ExternalClass {
                width: 100%;
            }
            div[style*="margin: 16px 0"] {
                margin: 0 !important;
            }
            table,
            td {
                mso-table-lspace: 0pt !important;
                mso-table-rspace: 0pt !important;
            }
            table {
                border-spacing: 0 !important;
                border-collapse: collapse !important;
                table-layout: fixed !important;
                margin: 0 auto !important;
            }
            table table table {
                table-layout: auto;
            }
            img {
                -ms-interpolation-mode: bicubic;
            }
            a[x-apple-data-detectors] {
                color: inherit !important;
            }
            @media only screen and (max-width: 500px) {
                td.col-2 {
                    display: block !important;
                    width: 100% !important;
                }
            }
        </style>
    </head>

    <body bgcolor="#e0e0e0" width="100%" style="margin: 0;" yahoo="yahoo">
        <table bgcolor="#e0e0e0" cellpadding="0" cellspacing="0" border="0" height="100%" width="100%" style="border-collapse:collapse;">
          <tr>
                <td background="'.$post->bg_url.'" bgcolor="#FFFFFF" valign="middle" style="text-align: center; background-position: center center !important; background-size: cover !important; background-repeat: no-repeat !important;">
                    <center style="width: 100%;">

                        <!-- Visually Hidden Preheader Text : BEGIN -->
                        <div style="display:none;font-size:1px;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;mso-hide:all;font-family: sans-serif;"> '.$text['excerpt'].' </div>
                        <!-- Visually Hidden Preheader Text : END -->

                        <!-- Email Header : BEGIN -->
                        <!--
                        <table align="center" class="email-container" bgcolor="#ffffff" style="width: 100%; max-width: 600px;">
                            <tr>
                                <td style="padding: 20px 0; text-align: center"><img src="https://corexp.com/wp-content/uploads/2017/11/cropped-corexplogotemp-copy.png" width="265" height="110" alt="alt_text" border="0">
                                </td>
                            </tr>
                        </table>
                        -->
                        <!-- Email Header : END -->

                        <!-- Email Body : BEGIN -->
                        <table cellspacing="0" cellpadding="0" border="0" align="center" bgcolor="#ffffff" class="email-container" style="width: 100%; max-width: 600px;">

                            <!-- Hero Image, Flush : BEGIN -->
                            <tr>
                                <td class="full-width-image"><img src="'.$post->img_url.'" width="600" alt="alt_text" border="0" align="center" style="width: 100%; max-width: 600px; height: auto;">
                                </td>
                            </tr>
                            <!-- Hero Image, Flush : END -->

                            <!-- 1 Column Text : BEGIN -->
                            <tr>
                                <td style="mso-height-rule: exactly;">
                                '.$text['content'].'
                                </td>
                            </tr>
                            <!-- 1 Column Text : BEGIN -->

                        </table>
                        <!-- Email Body : END -->

                        <!-- Email Footer : BEGIN -->
                        <table align="center" bgcolor="#d7d7d7" class="email-container email-footer" style="width: 100%; max-width: 600px;">
                            <tr>
                                <td style="padding: 40px 10px;width: 100%;font-size: 12px; font-family: sans-serif; mso-height-rule: exactly; line-height:18px; text-align: center; color: #333333;">
                                    <webversion style="text-decoration:underline; font-weight: bold;"><a href="'.esc_url( add_query_arg( 'cxp_email_2_web', $post->ID, $post->post_url ) ).'" title="View as a Web Page">View as a Web Page</a></webversion>
                                    <br>
                                    <br>
                                    <span class="mobile-link--footer">Carlsbad Naturals LLC<br>
                                        1712 Pioneer Ave, Suite 1923<br>
                                        Cheyenne, WY 82001<br>
                                        info@carlsbadnaturals.com • 1-951-595-2722
                                    </span>
                                    <br>
                                    <br>You are receiving this email as a customer of '.$post->site_url.'<br>
                                    <unsubscribe style="text-decoration:underline;"><a href="'.esc_url( add_query_arg( array('cxp_email_unsub_user' => $user->ID, 'cxp_email_unsub_post' => $post->ID), $post->post_url)).'" title="Unsubscribe from this list">unsubscribe</unsubscribe>
                                </td>
                            </tr>
                        </table>
                        <!-- Email Footer : END -->
                    </center>
                </td>
          </tr>
        </table>
        <img src="'.esc_url( add_query_arg( array('cxp_email_tracking_pixel' => $post->ID, 'user_id' => $user->ID), $post->site_url)).'">
    </body>
    </html>';

    $h2t = new html2text($html_message);
    $text_message = $h2t->get_text();

    $message['html'] = $html_message;
    $message['txt'] = $text_message;
	$message['subject'] = $text['title'];
    
    return $message;
}
?>