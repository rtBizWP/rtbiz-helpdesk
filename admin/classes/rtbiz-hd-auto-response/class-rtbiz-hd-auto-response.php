<?php
/**
 * Created by PhpStorm.
 * User: dips
 * Date: 24/2/15
 * Time: 4:13 PM
 */

if ( ! class_exists( 'Rtbiz_HD_Auto_Response' ) ) {
	/**
	 * Class Rt_HD_Auto_Response
	 */
	class Rtbiz_HD_Auto_Response {

	    private $weekdays;
	    private $weekend;

		/**
		 * Constructor
		 */
		public function __construct() {
			Rtbiz_HD::$loader->add_action( 'rtbiz_hd_auto_response', $this, 'auto_response', 10, 2 );

	        $this->weekdays = array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
	        $this->weekend = apply_filters( 'rthd_autoresponse_weekends', array( 'Saturday', 'Sunday' ) );
		}

		/**
		 * auto response functionality : add auto followup when ticket or followup created
		 *
		 * @param $comment_post_ID
		 * @param $post_date
		 */
		function  auto_response( $comment_post_ID, $post_date ) {
			global $rtbiz_hd_import_operation;
			$redux = rtbiz_hd_get_redux_settings();
			$isEnableAutoResponse = ( isset( $redux['rthd_enable_auto_response'] ) && 1 == $redux['rthd_enable_auto_response'] );
			$isDayShift = ( isset( $redux['rthd_enable_auto_response_mode'] ) && 1 == $redux['rthd_enable_auto_response_mode'] );
	        $isWeekEndsOnly = ( isset( $redux['rthd_autoresponse_weekend'] ) && 1 == $redux['rthd_autoresponse_weekend'] ) ;
			$placeholder_list = array();

			if ( $isEnableAutoResponse ) {
				$d               = new DateTime( $post_date );
				$UTC             = new DateTimeZone( 'UTC' );
				$d->setTimezone( $UTC );
				$commenttime    = gmdate( 'Y-m-d H:i:s', $d->getTimestamp() );
				$timeStamp      = intval( $d->getTimestamp() ) + ( get_option( 'gmt_offset' ) * 3600 );
				$day = date( 'N', $timeStamp ) - 1; // date returns 1 for monday & 7 for  sunday
				$dayname = date( 'l', $timeStamp );
				$hour = date( 'H', $timeStamp );

	            // Auto response enble only weekend
	            if ( $isWeekEndsOnly && ! in_array( $dayname, $this->weekend ) ) {
		            return;
	            }

				$userid = get_post_field( 'post_author', $comment_post_ID ); //post author
				$contact_id = rtbiz_hd_get_contact_id_by_user_id( $userid, true );
				$comment_author = 'Helpdesk Bot';
				$comment_author_email = '';
				$comment_content = rtbiz_hd_get_auto_response_message();

				if ( $isDayShift ) {
					$shifttime = array();
					$shifttime['start'] = isset( $redux['rthd_dayshift_time_start'] ) ? $redux['rthd_dayshift_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					$shifttime['end'] = isset( $redux['rthd_dayshift_time_end'] ) ? $redux['rthd_dayshift_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					if ( ! empty( $shifttime['start'] ) ) {

						// if [ time not empty and off time ] or [ time is empty ]
						if ( ( -1 != $shifttime['start'][ $day ] && -1 != $shifttime['end'][ $day ] && ( $hour < $shifttime['start'][ $day ] || $hour > $shifttime['end'][ $day ] ) ) || ( -1 == $shifttime['start'][ $day ] && -1 == $shifttime['end'][ $day ] ) ) {
							// Get next Working hours
	                        $isSameDay = false;
	                        $nextday = ( $day + 1 );
	                        if ( $hour < $shifttime['start'][ $day ] ) {
		                        $nextday = $day;
		                        $isSameDay = true;
	                        }
	                        $nextday = $this->next_day( $nextday, $shifttime, $isDayShift );

							//get next staring time
							$NextStatingTime = $shifttime['start'][ $nextday ];
							// check nextday is same day or not
							if ( ! $isSameDay ) {
								$nextday = $this->weekdays[ $nextday ] . ' after ';
							} else {
								$nextday = 'Today after ';
							}
							$nextday .= ( $NextStatingTime > 12 ) ? ( 0 == ( $NextStatingTime - 12 ) ? '00:01' : $NextStatingTime - 12 )  . ' PM' : ( 0 == $NextStatingTime ? '0:01' : $NextStatingTime ) . ' AM';
							$placeholder_list['NextStartingHour'] = $nextday;

							foreach ( $placeholder_list as $key => $value ) {
								$comment_content = str_replace( '{' . $key . '}', $value, $comment_content );
							}

							$rtbiz_hd_import_operation->insert_post_comment( $comment_post_ID, $contact_id , $comment_content, $comment_author, $comment_author_email, $commenttime, array(), array(), array(), '', '', '', array(), '', Rtbiz_HD_Import_Operation::$FOLLOWUP_BOT, 0, true );

						}
					}
				} else {
					$shifttime = array();
					$shifttime['am_start'] = isset( $redux['rthd_daynight_am_time_start'] ) ? $redux['rthd_daynight_am_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					$shifttime['am_end'] = isset( $redux['rthd_daynight_am_time_end'] ) ? $redux['rthd_daynight_am_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					$shifttime['pm_start'] = isset( $redux['rthd_daynight_pm_time_start'] ) ? $redux['rthd_daynight_pm_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					$shifttime['pm_end'] = isset( $redux['rthd_daynight_pm_time_end'] ) ? $redux['rthd_daynight_pm_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
					if ( ! empty( $shifttime ) ) {

						// if [ time not empty and off time ] or [ time is empty ]
						if ( ( ( -1 != $shifttime['am_start'][ $day ] && -1 != $shifttime['am_end'][ $day ] && -1 != $shifttime['pm_start'][ $day ] && -1 != $shifttime['pm_end'][ $day ] ) && ( ( $hour < $shifttime['am_start'][ $day ] || $hour > $shifttime['am_end'][ $day ] ) && ( $hour < $shifttime['pm_start'][ $day ] || $hour > $shifttime['pm_end'][ $day ] ) ) ) || // all time are not empty
							 ( -1 == $shifttime['am_start'][ $day ] && -1 == $shifttime['am_end'][ $day ] && -1 == $shifttime['pm_start'][ $day ] && -1 == $shifttime['pm_end'][ $day ] ) || // all time are emtpty
							 ( ( -1 != $shifttime['am_start'][ $day ] && -1 != $shifttime['am_end'][ $day ] && -1 == $shifttime['pm_start'][ $day ] && -1 == $shifttime['pm_end'][ $day ]  ) && ( $hour < $shifttime['am_start'][ $day ] || $hour > $shifttime['am_end'][ $day ] ) ) || // am time is not empty but pm  is time empty
							 ( ( -1 == $shifttime['am_start'][ $day ] && -1 == $shifttime['am_end'][ $day ] && -1 != $shifttime['pm_start'][ $day ] && -1 != $shifttime['pm_end'][ $day ]  ) && ( $hour < $shifttime['pm_start'][ $day ] || $hour > $shifttime['pm_end'][ $day ] ) ) // am time is empty but pm time is not empty
						   ) {
							// Get next Working hours
							$isSameDay = false;
	                        $nextday = ( $day + 1 );
							if (   $hour < $shifttime['am_start'][ $day ] || ( $hour > $shifttime['am_end'][ $day ] && $hour < $shifttime['pm_start'][ $day ] )  ) {
								$nextday = $day;
	                            $isSameDay = true;
							}

							$nextday = $this->next_day( $nextday, $shifttime, $isDayShift );
							//get next staring time

							if ( -1 != $shifttime['am_start'][ $nextday ] ) {
								$NextStatingTime = $shifttime['am_start'][ $nextday ];
							} else {
								$NextStatingTime = $shifttime['pm_start'][ $nextday ];
							}

							if (  $isSameDay && $hour >= 12  && $NextStatingTime < $hour && $nextday == $day ) {
								$NextStatingTime = $shifttime['pm_start'][ $nextday ];
							}

							// check nextday is same day or not
							if ( ! $isSameDay ) {
								$nextday = $this->weekdays[ $nextday ] . ' after ';
							} else {
								$nextday = 'Today after ';
							}
							$nextday .= ( $NextStatingTime > 12 ) ? ( 0 == ( $NextStatingTime - 12 ) ? '00:01' : $NextStatingTime - 12 )  . ' PM' : ( 0 == $NextStatingTime ? '0:01' : $NextStatingTime ) . ' AM';
							$placeholder_list['NextStartingHour'] = $nextday;

							foreach ( $placeholder_list as $key => $value ) {
								$comment_content = str_replace( '{' . $key . '}', $value, $comment_content );
							}

							$rtbiz_hd_import_operation->insert_post_comment( $comment_post_ID, $contact_id, $comment_content, $comment_author, $comment_author_email, $commenttime, array(), array(), array(), '', '', '', array(), '', Rtbiz_HD_Import_Operation::$FOLLOWUP_BOT, 0, true );
						}
					}
				}
			}
		}

		/**
		 * get next day which is working day
		 *
		 * @param $day
		 * @param $shifttime
		 * @param $isDayShift
		 * @return int
		 */
		function next_day( $day, $shifttime, $isDayShift ) {
			if ( $day < 0 || $day > 7 ) {
				return false;
			}
			if ( $day > 6 ) {
				$day = 0;
			}

			// next day office time set or not
			if ( $isDayShift ) {
				if ( ! empty( $shifttime ) ) {
					if ( -1 == $shifttime['start'][ $day ] || -1 == $shifttime['end'][ $day ] ) {
						return $this->next_day( $day + 1, $shifttime, $isDayShift );
					}
				}
			} else {
				if ( ! empty( $shifttime ) ) {
					if ( ( -1 == $shifttime['am_start'][ $day ] || -1 == $shifttime['am_end'][ $day ] ) && ( -1 == $shifttime['pm_start'][ $day ] || -1 == $shifttime['pm_end'][ $day ] ) ) {
						return $this->next_day( $day + 1, $shifttime, $isDayShift );
					}
				}
			}

			return $day;
		}

		/**
		 * UI for dayshift setting
		 */
		function setting_dayshift_ui() {
			$redux = rtbiz_hd_get_redux_settings();
			$shifttime = array();
	        $shifttime['start'] = isset( $redux['rthd_dayshift_time_start'] ) ? $redux['rthd_dayshift_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
			$shifttime['end'] = isset( $redux['rthd_dayshift_time_end'] ) ? $redux['rthd_dayshift_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 ); ?>
	        <span id="rthd-response-day-error" class="myerror rthd-hide-row"></span>
	        <table id="rthd-response">
                <tbody>
                <?php foreach ( $this->weekdays as $key => $weekday ) :
	                $class = ( in_array( $weekday, $this->weekend ) ) ? 'weekend' : 'weekday'; ?>
	                <tr class="rthd-dayshift-info <?php echo $class; ?>">
                        <td>
                            <label><?php echo $weekday; ?></label>
                        </td>
                        <td>
                            <select id="rthd-dayshift-time-start-<?php echo $key; ?>" class="rthd-dayshift-time-start" name="redux_helpdesk_settings[rthd_dayshift_time_start][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option>
                                <?php for ( $i = 0; $i < 24; $i++ ) {
									$selected = ( isset( $shifttime['start'][ $key ] ) && $shifttime['start'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':00' ) ) . '</option>'. "\n" ;
} ?>
                            </select>
                            &nbsp;&nbsp;To&nbsp;&nbsp;
                            <select id="rthd-dayshift-time-end-<?php echo $key; ?>" class="rthd-dayshift-time-end" name="redux_helpdesk_settings[rthd_dayshift_time_end][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option>
                                <?php for ( $i = 0; $i < 24; $i++ ) {
									$selected = ( isset( $shifttime['end'][ $key ] ) && $shifttime['end'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':59' ) ) . '</option>'. "\n" ;
} ?>
                            </select>
                        </td>
		                <td><?php
			                if ( 0 === $key ){ ?>
								<a href="" id="rthd-dayshift-time-set-all">Set for all</a><?php
			                }?>
		                </td>
                    </tr>
	                <tr class="rthd-dayshift-error rthd-hide-row">
                        <td>&nbsp</td>
                        <td class="error"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php
		}

		/**
		 * UI for daynightshift setting
		 */
		function setting_daynightshift_ui() {
			$redux = rtbiz_hd_get_redux_settings();
			$shifttime = array();
	        $shifttime['am_start'] = isset( $redux['rthd_daynight_am_time_start'] ) ? $redux['rthd_daynight_am_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
			$shifttime['am_end'] = isset( $redux['rthd_daynight_am_time_end'] ) ? $redux['rthd_daynight_am_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
			$shifttime['pm_start'] = isset( $redux['rthd_daynight_pm_time_start'] ) ? $redux['rthd_daynight_pm_time_start'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 );
			$shifttime['pm_end'] = isset( $redux['rthd_daynight_pm_time_end'] ) ? $redux['rthd_daynight_pm_time_end'] : array( 0 => -1, 1 => -1, 2 => -1, 3 => -1, 4 => -1, 5 => -1, 6 => -1 ); ?>
	        <span id="rthd-response-daynight-error" class="myerror rthd-hide-row"></span>
	        <table id="rthd-response">
                <tbody>
                <tr>
                    <th>&nbsp;</th>
                    <th>AM</th>
                    <th>PM</th>
                </tr>
                <?php foreach ( $this->weekdays as $key => $weekday ) :
	                $class = ( in_array( $weekday, $this->weekend ) ) ? 'weekend' : 'weekday'; ?>
                    <tr class="rthd-daynightshift-info <?php echo $class; ?>">
                        <td>
                            <label><?php echo $weekday; ?></label>
                        </td>
                        <td>
                            <select id="rthd-daynight-am-time-start-<?php echo $key; ?>" class="rthd-daynight-am-time-start" name="redux_helpdesk_settings[rthd_daynight_am_time_start][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option><?php
	                            for ( $i = 0; $i <= 11; $i++ ) {
									$selected = ( isset( $shifttime['am_start'][ $key ] ) && $shifttime['am_start'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':00' ) ) . '</option>'. "\n" ;
								} ?>
                            </select>
                            &nbsp;To&nbsp;
                            <select id="rthd-daynight-am-time-end-<?php echo $key; ?>" class="rthd-daynight-am-time-end" name="redux_helpdesk_settings[rthd_daynight_am_time_end][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option>';<?php
	                            for ( $i = 0; $i <= 11; $i++ ) {
									$selected = ( isset( $shifttime['am_end'][ $key ] ) && $shifttime['am_end'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':59' ) ) . '</option>'. "\n" ;
								} ?>
                            </select>
                        </td>
                        <td>
                            <select id="rthd-daynight-pm-time-start-<?php echo $key; ?>" class="rthd-daynight-pm-time-start" name="redux_helpdesk_settings[rthd_daynight_pm_time_start][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option><?php
	                            for ( $i = 12; $i <= 23; $i++ ) {
									$selected = ( isset( $shifttime['pm_start'][ $key ] ) && $shifttime['pm_start'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':00' ) ) . '</option>'. "\n" ;
								} ?>
                            </select>
                            &nbsp;To&nbsp;
                            <select id="rthd-daynight-pm-time-end-<?php echo $key; ?>" class="rthd-daynight-pm-time-end" name="redux_helpdesk_settings[rthd_daynight_pm_time_end][<?php echo $key; ?>]">
                                <option value="-1">Select Time</option>';<?php
	                            for ( $i = 12; $i <= 23; $i++ ) {
									$selected = ( isset( $shifttime['pm_end'][ $key ] ) && $shifttime['pm_end'][ $key ] == $i ) ? 'selected' : '';
									echo '<option value="'.$i.'" ' . $selected . '>' . date( 'H:i', strtotime( $i . ':59' ) ) . '</option>'. "\n" ;
								} ?>
                            </select>
                        </td>
	                    <td><?php
		                    if ( 0 === $key ){ ?>
			                    <a href="javascript;" id="rthd-daynight-time-set-all">Set for all</a><?php
		                    }?>
	                    </td>
                    </tr>

                    <tr class="rthd-daynightshift-error rthd-hide-row">
                        <td>&nbsp</td>
                        <td class="am-time-error"></td>
                        <td class="pm-time-error"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php
		}
	}
}
