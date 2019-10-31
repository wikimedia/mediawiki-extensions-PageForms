/**
 * Code to integrate the FullCalendar JavaScript library into Page Forms.
 *
 * @author Priyanshu Varshney
 */
/* global moment */
( function ( $, mw, pf ) {
	'use strict';

	$( '.pfFullCalendarJS' ).each( function() {

		$( '#fullCalendarLoading' ).css("display", "block");

		// This counter is used to assign unique ids to the calendar events.
		// If the event is deleted, we lose that unique id and can't be used gain.
		var counter = 0;
		var monthNames = mw.config.get('monthMessages');
		// Stuff from PF_FormPrinter.php
		var calendarParams = mw.config.get( 'wgPageFormsCalendarParams' );
		var calendarGridValues = mw.config.get( 'wgPageFormsCalendarValues' );
		var calendarHTML = mw.config.get('wgPageFormsCalendarHTML');

		var $fcDiv = $( this );
		var templateName = $fcDiv.attr( 'template-name' );
		var templateNameCopy = templateName;
		var eventTitleField = $fcDiv.attr( 'title-field' );
		var eventDateField = $fcDiv.attr( 'event-date-field' );
		var eventStartDateField = $fcDiv.attr( 'event-start-date-field' );
		var eventEndDateField = $fcDiv.attr( 'event-end-date-field' );
		var flagOneDayEvent = true;
		var pageLoaded = false;
		var isEventEndDateTime = false;
		var isEventStartTime = false;

		if( eventDateField === undefined ) {

			flagOneDayEvent = false;
		}

		var fieldType=[];
		var englishMonthNames = [ 'January', 'February',
			'March', 'April', 'May', 'June', 'July',
			'August', 'September', 'October', 'November',
			'December' ];

		// From here the game begins - getting the form HTML to be used as the popup form  -
		// for the calendar interface
		var form_html = calendarHTML[templateName];

		var popup = '<form id="popupForm">';
		var delete_button = '<button  id="event_delete" class = "delete-event-button" name="data" type="button" >' + mw.msg('pf-calendar-deleteevent') + '</button>';
		var create_button = '<button  id="form_submit" class = "submit-event-button" name="data" type="button" >' + mw.msg('pf-calendar-createevent') + '</button>';
		var update_button = '<button  id="form_submit" class = "submit-event-button" name="data" type="button" >' + mw.msg('pf-calendar-updateevent') + '</button>';
		popup += form_html;
		var createEventPopup = popup + create_button;
		var updateEventPopup = popup + update_button;
		var suitableForCalendar = true;
		var calendarIdSelector = '#' + templateName + "FullCalendar";
		var events = [], data = [], dateFields = [], dateStartFields = [], dateEndFields = [], eventsNoDate = [], checkboxes_num = [];
		var segment, dateSegment, year_fc, month_fc, date_fc,
				timeSegment, hour_fc, minute_fc, second_fc, ampm24h,
				dateEntry, monthEntry, yearEntry, hourEntry,
				minuteEntry, secondEntry, ampm24hEntry, regularEntry,
				eventData, preEventData, currParam, temp, titleIndex, monthIndex,
				eventDate, eventDateDay,eventDateYear, eventDateMonth, eventDateHour,
				eventStartDate, eventEndDate , eventDateMinute, eventDateSecond, eventDateAmPm24h, reserveDate, id_form,
				eventStartDateDay, eventStartDateYear, eventStartDateMonth, eventStartDateHour,
				eventStartDateMinute, eventStartDateSecond, eventStartDateAmPm24;
		var currentEndDateMoment;
		var checkboxes_values =[];
		var tokens_proto, combobox_proto, result, eventTemplateName, parameterName, event_contents, all_events,
				dateElement, next_date, formatted, i, j;

		var autoFillDay = templateName + '[cf][' + eventDateField + '][day]',
				autoFillMonth = templateName + '[cf][' + eventDateField + '][month]',
				autoFillYear = templateName + '[cf][' + eventDateField + '][year]',
				autoFillHour = templateName+'[cf]['+ eventDateField + '][hour]',
				autoFillMinute = templateName+'[cf]['+ eventDateField + '][minute]',
				autoFillSecond = templateName+'[cf]['+ eventDateField + '][second]',
				autoFillAmPm24h = templateName+'[cf]['+ eventDateField + '][ampm24h]',
				autoFillStartDay = templateName + '[cf][' + eventStartDateField + '][day]',
				autoFillStartMonth = templateName + '[cf][' + eventStartDateField + '][month]',
				autoFillStartYear = templateName + '[cf][' + eventStartDateField + '][year]',
				autoFillEndDay = templateName + '[cf][' + eventEndDateField + '][day]',
				autoFillEndMonth = templateName + '[cf][' + eventEndDateField + '][month]',
				autoFillEndYear = templateName + '[cf][' + eventEndDateField + '][year]',
				autoFillStartHour = templateName+'[cf]['+ eventStartDateField + '][hour]',
				autoFillStartMinute = templateName+'[cf]['+ eventStartDateField + '][minute]',
				autoFillStartSecond = templateName+'[cf]['+ eventStartDateField + '][second]',
				autoFillStartAmPm24h = templateName+'[cf]['+ eventStartDateField + '][ampm24h]',
				autoFillEndHour = templateName+'[cf]['+ eventEndDateField + '][hour]',
				autoFillEndMinute = templateName+'[cf]['+ eventEndDateField + '][minute]',
				autoFillEndSecond = templateName+'[cf]['+ eventEndDateField + '][second]',
				autoFillEndAmPm24h = templateName+'[cf]['+ eventEndDateField + '][ampm24h]';

		for( i = 0; i<calendarParams[templateName].length; i++ ) {
			fieldType[calendarParams[templateName][i].name]=calendarParams[templateName][i].type;
		}

		if( fieldType[eventStartDateField] === "datetime" || fieldType[eventDateField] === "datetime" ) {
			isEventStartTime = true;
		}

		if( fieldType[eventEndDateField] === "datetime") {
			isEventEndDateTime = true;
		}

		function saveData( flag ) {
			if(flag === 'single' ) {
				data = $('#popupForm').serializeArray();
				titleIndex = -1;
				dateFields = [];
				eventDate = '';
				for(j=0;j<data.length;j++){
					result = data[j].name.split("[");
					eventTemplateName = result[0];
					parameterName = result[2].split("]")[0];
					if( parameterName === eventTitleField ) {
						if( titleIndex === -1 ) {
							titleIndex = j;
						}
					} else if ( parameterName === eventDateField ) {
							dateFields.push({'name':data[j].name,'value':data[j].value});
							if( data[j].name.includes('second') ) {
								if( data[j].value === '' ) {
									data[j].value = '59';
								}
							} else if ( data[j].name.includes('hour') ) {
								if( data[j].value === '' ) {
									data[j].value = '23';
								}
							} else if ( data[j].name.includes('minute') ) {
								if( data[j].value === '' ) {
									data[j].value = '59';
								}
							}
					}
				}
			} else if ( flag === 'multiple' ) {
				data = $('#popupForm').serializeArray();
				titleIndex = -1;
				dateStartFields = [];
				dateEndFields = [];
				eventStartDate = '';
				eventEndDate = '';
				for(j=0;j<data.length;j++) {
					result = data[j].name.split("[");
					eventTemplateName = result[0];
					parameterName = result[2].split("]")[0];

					if( parameterName === eventTitleField ) {
						if( titleIndex === -1 ) {
							titleIndex = j;
						}
					} else if ( parameterName === eventStartDateField ) {
							dateStartFields.push({'name':data[j].name,'value':data[j].value});
							if( data[j].name.includes('second') ) {
								if( data[j].value === '' ) {
									data[j].value = '00';
								}
							} else if ( data[j].name.includes('hour') ) {
								if( data[j].value === '' ) {
									data[j].value = '00';
								}
							} else if ( data[j].name.includes('minute') ) {
								if( data[j].value === '' ) {
									data[j].value = '00';
								}
							}
					} else if ( parameterName === eventEndDateField ) {
							dateEndFields.push({'name':data[j].name,'value':data[j].value});
							if( data[j].name.includes('second') ) {
								if( data[j].value === '' ) {
									data[j].value = '59';
								}
							} else if ( data[j].name.includes('hour') ) {
								if( data[j].value === '' ) {
									data[j].value = '23';
								}
							} else if ( data[j].name.includes('minute') ) {
								if( data[j].value === '' ) {
									data[j].value = '59';
								}
							}
					}
				}
			}
		}
		// Checks if the date field parts are empty or not - if not,
		// don't allow the user to submit
		function checkAndSave( flag ) {
			if( flag === 'single' ) {
				$( ':input' ).on('keyup',function() {
					if(
						$( ':input[name="' + autoFillDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillMonth + '"]' ).val() === ''
					) {
						$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
					} else {
						$("#form_submit").removeAttr("disabled").css({'background-color': '#3366CC','color': 'white'});
					}
				});
			} else if ( flag === 'multiple' ) {
				$( ':input' ).on('keyup',function() {
					if(
						$( ':input[name="' + autoFillStartDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndDay + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillStartYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndYear + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillStartMonth + '"]' ).val() === '' ||
						$( ':input[name="' + autoFillEndMonth + '"]' ).val() === ''
					) {
						$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
					} else {
						var date1, date2;
						if( mw.config.get('wgAmericanDates') ) {
							date1 = $( ':input[name="' + autoFillStartYear + '"]' ).val() + '-' +
							padNumber(englishMonthNames.indexOf($( ':input[name="' + autoFillStartMonth + '"]' ).val()))  + '-' +
							padNumber($( ':input[name="' + autoFillStartDay + '"]' ).val());
							date2 = $( ':input[name="' + autoFillEndYear + '"]' ).val() + '-' +
							padNumber(englishMonthNames.indexOf($( ':input[name="' + autoFillEndMonth + '"]' ).val()))  + '-' +
							padNumber($( ':input[name="' + autoFillEndDay + '"]' ).val());
						} else {
							date1 = $( ':input[name="' + autoFillStartYear + '"]' ).val() + '-' +
							$( ':input[name="' + autoFillStartMonth + '"]' ).val()  + '-' +
							padNumber($( ':input[name="' + autoFillStartDay + '"]' ).val());
							date2 = $( ':input[name="' + autoFillEndYear + '"]' ).val() + '-' +
							$( ':input[name="' + autoFillEndMonth + '"]' ).val()  + '-' +
							padNumber($( ':input[name="' + autoFillEndDay + '"]' ).val());
						}
						if( $( ':input[name="' + autoFillStartHour + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillStartMinute + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillStartSecond	 + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndHour	 + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndMinute	 + '"]' ).val() !== undefined &&
								$( ':input[name="' + autoFillEndSecond	 + '"]' ).val() !== undefined) {
							date1 += ' ' + $( ':input[name="' + autoFillStartHour + '"]' ).val() + ':'
							+ $( ':input[name="' + autoFillStartMinute + '"]' ).val() + ':'
							+ $( ':input[name="' + autoFillStartSecond + '"]' ).val();
							date2 += ' ' + $( ':input[name="' + autoFillEndHour + '"]' ).val() + ':'
							+ $( ':input[name="' + autoFillEndMinute + '"]' ).val() + ':'
							+ $( ':input[name="' + autoFillEndSecond + '"]' ).val();
						}
						date1 = moment(date1);
						date2 = moment(date2);
						if( date1<=date2 ) {
							$("#form_submit").removeAttr("disabled").css({'background-color': '#3366CC','color': 'white'});
						} else {
							$("#form_submit").attr("disabled", "disabled").css({'background-color':'#c8ccd1','color':'#fff'});
						}
					}
				});
			}
		}

		function resetDateAndTime() {
			eventDateDay = '01';
			eventDateYear = '2010';
			eventDateMonth = '00';
			eventDateHour = '00';
			eventDateMinute = '00';
			eventDateSecond = '00';
			eventDateAmPm24h = "";
		}
		// flag : 0 - one day , 1 - start, 2 - end
		function setDateAndTime( arrData, flag ) {
			for( var dateEntry=0; dateEntry < arrData.length ; dateEntry++ ) {
				if( arrData[dateEntry].name.includes('year') ) {
					eventDateYear = arrData[dateEntry].value;
				} else if ( arrData[dateEntry].name.includes('month') ) {
					if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
						var monthIndex =  englishMonthNames.indexOf( arrData[dateEntry].value );
						eventDateMonth = padNumber(monthIndex + 1);
					} else {
						eventDateMonth = padNumber(arrData[dateEntry].value);
					}
				} else if ( arrData[dateEntry].name.includes('day') ) {
					eventDateDay = padNumber( arrData[dateEntry].value ) ;
				} else if ( arrData[dateEntry].name.includes('hour') ) {
					eventDateHour = arrData[dateEntry].value ;
					if( eventDateHour === '' ) {
						if ( flag === '1' ) {
							eventDateHour = '00';
						} else {
							eventDateHour = '23';
						}
					}
				} else if ( arrData[dateEntry].name.includes('minute') ) {
					eventDateMinute = arrData[dateEntry].value ;
					if( eventDateMinute === '' ) {
						if ( flag === '1' ) {
							eventDateMinute = '00';
						} else {
							eventDateMinute = '59';
						}
					}
				} else if ( arrData[dateEntry].name.includes('second') ) {
					eventDateSecond = arrData[dateEntry].value ;
					if( eventDateSecond === '' ) {
						if ( flag === '1' ) {
							eventDateSecond = '00';
						} else {
							eventDateSecond = '59';
						}
					}
				} else if ( arrData[dateEntry].name.includes('ampm24h') ) {
					eventDateAmPm24h = arrData[dateEntry].value ;
				}
			}
		}
		function checkDateTime( arrData, date ) {
			if( arrData.length === 7 ) {
				if( eventDateAmPm24h  === "" ) {
					date = date + 'T' + padNumber( eventDateHour ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
				} else if ( eventDateAmPm24h === "AM" ) {
					if( eventDateHour === "12" ) {
						date = date + 'T' + '00' + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					} else {
							date = date + 'T' + padNumber( eventDateHour ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					}
				} else if ( eventDateAmPm24h === "PM" ) {
					if( eventDateHour === "12" ) {
						date = date + 'T' + '12' + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					} else {
							date = date + 'T' + padNumber( parseInt(eventDateHour) + 12 ) + ':' + padNumber( eventDateMinute ) + ':' + padNumber( eventDateSecond );
					}
				}
			}
			return date;
		}
		// check if the date/ datetime formats are suitable for the calendar eventStartDate
		// if not these are not proper events. They will be stored in eventsNoDate

		function isValidDate(dateString) {
			if( mw.config.get( 'wgAmericanDates' ) ) {
				var reg = /^(January?|February?|March?|April?|May|June?|July?|August?|September?|October?|November?|December?)\s\d{1,2},\s\d{4}$/;
				if(!dateString.match(reg)) { return false; }
			} else {
				dateString = dateString.replace('/','-');
				dateString = dateString.replace('/','-');
				var regEx = /^\d{4}-\d{2}-\d{2}$/;
				if(!dateString.match(regEx)) { return false; }  // Invalid format
				var d = new Date(dateString);
				var dNum = d.getTime();
				if(!dNum && dNum !== 0) { return false; } // NaN value, Invalid date
				return d.toISOString().slice(0,10) === dateString;
			}
		}
		function dateTimeValidation(dateString) {
			if( mw.config.get( 'wgAmericanDates' ) ) {
				var reg = /^(January?|February?|March?|April?|May|June?|July?|August?|September?|October?|November?|December?)\s\d{1,2},\s\d{4}\s\d{2}:\d{2}:\d{2}$/;
				if(!dateString.match(reg)) { return false; }
			} else {
				dateString = dateString.replace('/','-');
				dateString = dateString.replace('/','-');
				var regEx = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/;
				if(!dateString.match(regEx)) { return false; }  // Invalid format
				return !!new Date(dateString).getTime();
			}
		}

		function addDays(myDate,days) {
			return new Date(myDate.getTime() + days*24*60*60*1000);
		}

		function padNumber(number) {
			var string  = '' + number;
			string      = string.length < 2 ? '0' + string : string;
			return string;
		}

		$( calendarIdSelector ).fullCalendar({

			editable: true,
			eventLimit: true, // when too many events in a day, show the popover
			header: {
				left: 'prev,next today',
				center: 'title',
				right: 'month,agendaWeek,agendaDay'
			},
			navLinks: true, // can click day/week names to navigate views
			selectable: true,
			selectHelper: true,
			nextDayThreshold: "00:00:00",

			// Populate the calendar with the already saved events - if any
			events: function( start, end, timezone, callback ) {
				var calendarValues = calendarGridValues[templateNameCopy];
				for( i = 0; i<calendarValues.length; i++ ) {
					data = [];
					eventData = calendarValues[i];
					for( j=0; j<calendarParams[templateNameCopy].length;j++ ) {
						currParam = calendarParams[templateNameCopy][j];
						temp = eventData[currParam.name];
						if( fieldType[currParam.name] === 'date' && isValidDate(temp) === false ) {
							eventsNoDate.push(eventData);
							suitableForCalendar = false;
						} else if ( fieldType[currParam.name] === 'datetime' && dateTimeValidation(temp) === false ) {
							eventsNoDate.push(eventData);
							suitableForCalendar = false;
						}
					}
					if(suitableForCalendar === true ) {
						for( j=0; j<calendarParams[templateNameCopy].length;j++ ) {
							currParam = calendarParams[templateNameCopy][j];
							temp = eventData[currParam.name];
							if( fieldType[currParam.name] === 'date' ) {
								if ( mw.config.get( 'wgAmericanDates' ) ) { //check for date-style format.
									dateSegment = temp.split(' ');
									year_fc = dateSegment[2];
									month_fc = dateSegment[0];
									date_fc = padNumber(dateSegment[1].split(',')[0]);
								} else {
									dateSegment = temp.split('/');
									year_fc = dateSegment[0];
									month_fc = dateSegment[1];
									date_fc = padNumber(dateSegment[2]);
								}
								dateEntry = {
									'name' : templateNameCopy + '[cf]['+currParam.name+'][day]',
									'value': date_fc
								};
								data.push( dateEntry );
								monthEntry = {
									'name' : templateNameCopy + '[cf]['+currParam.name+'][month]',
									'value': month_fc
								};
								data.push( monthEntry );
								yearEntry = {
									'name' : templateNameCopy + '[cf]['+currParam.name+'][year]',
									'value': year_fc
								};
								data.push( yearEntry );
							} else if ( fieldType[currParam.name] === "datetime" ) {
								if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
									dateSegment = temp.split(' ');
									year_fc = dateSegment[2];
									month_fc = dateSegment[0];
									date_fc = padNumber(dateSegment[1].split(',')[0]);
									timeSegment = dateSegment[3].split(':');
									hour_fc = timeSegment[0];
									minute_fc = timeSegment[1];
									second_fc = timeSegment[2];
									ampm24h = dateSegment[4];
									if( ampm24h === undefined ) {
										ampm24h = '';
									}
								} else {
									segment = temp.split(' '); // will be used
									dateSegment = segment[0].split('/');
									timeSegment = segment[1].split(':');
									year_fc = dateSegment[0];
									month_fc = dateSegment[1];
									date_fc = padNumber(dateSegment[2]);
									hour_fc = timeSegment[0];
									minute_fc = timeSegment[1];
									second_fc = timeSegment[2];
									ampm24h = segment[2];
									if( ampm24h === undefined ) {
										ampm24h = '';
									}
								}

								dateEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][day]',
									'value': date_fc
								};
								data.push( dateEntry );
								monthEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][month]',
									'value': month_fc
								};
								data.push( monthEntry );
								yearEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][year]',
									'value': year_fc
								};
								data.push(yearEntry);
								hourEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][hour]',
									'value': hour_fc
								};
								data.push( hourEntry );
								minuteEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][minute]',
									'value': minute_fc
								};
								data.push( minuteEntry );
								secondEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][second]',
									'value': second_fc
								};
								data.push( secondEntry );
								ampm24hEntry = {
									'name' : templateNameCopy+'[cf]['+currParam.name+'][ampm24h]',
									'value': ampm24h
								};
								data.push( ampm24hEntry );
							} else {
								regularEntry = {
									'name': templateNameCopy+'[cf]['+currParam.name+']',
									'value':temp
								};
								data.push( regularEntry );
							}
						}
					if( flagOneDayEvent === true ) {
						titleIndex = -1;
						dateFields = [];
						eventDate = '';
						for(j=0;j<data.length;j++){
							result = data[j].name.split("[");
							eventTemplateName = result[0];
							parameterName = result[2].split("]")[0];

							if( parameterName === eventTitleField ) {
								if( titleIndex === -1 ) {
									titleIndex = j;
								}
							} else if ( parameterName === eventDateField ) {
									dateFields.push({'name':data[j].name,'value':data[j].value});
							}
						}
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventDate;
						eventDate = checkDateTime( dateFields, eventDate );
						id_form = eventDate + "_fc" + counter;
						preEventData = {
							title: data[titleIndex].value,
							start: eventDate,
							end: reserveDate + 'T23:59:59',
							contents: data,
							id:id_form
						};
						if(!pageLoaded){
							counter++;
							$( calendarIdSelector ).fullCalendar( 'renderEvent', preEventData, true );
						}
					} else {
						titleIndex = -1;
						dateStartFields = [];
						dateEndFields = [];
						eventStartDate = '';
						eventEndDate = '';
						for( j=0; j<data.length; j++ ){
							result = data[j].name.split("[");
							eventTemplateName = result[0];
							parameterName = result[2].split("]")[0];

							if( parameterName === eventTitleField ) {
								if( titleIndex === -1 ) {
									titleIndex = j;
								}
							} else if ( parameterName === eventStartDateField ) {
									dateStartFields.push({'name':data[j].name,'value':data[j].value});
							} else if ( parameterName === eventEndDateField ) {
									dateEndFields.push({'name':data[j].name,'value':data[j].value});
							}
						}
						resetDateAndTime();
						setDateAndTime( dateStartFields );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventStartDate;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );
						resetDateAndTime();
						setDateAndTime( dateEndFields );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventEndDate;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );
						id_form = eventStartDate +"_fc"+counter;
						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement      = new Date(eventEndDate);
							next_date = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = next_date.getUTCFullYear() + '-' + padNumber(next_date.getUTCMonth() + 1) + '-' + padNumber(next_date.getUTCDate());
							eventEndDate = formatted;
						}
						preEventData = {
							title  : eventData[eventTitleField],
							start  : eventStartDate,
							end : eventEndDate,
							contents: data,
							id:id_form
						};
						if(!pageLoaded){
							counter++;
							$( calendarIdSelector ).fullCalendar( 'renderEvent', preEventData, true );
						}

					}
				}
				suitableForCalendar = true;
				}
				callback(events);
				pageLoaded = true;
			},

			// Select : JS method to put events on the calendar by selecting
			// one or more than one dates
			select : function( start, end ) {
				// Open the popup form to allow the user to create the event
				$.fancybox.open( createEventPopup + '</form>' );
				$("[class|='fancybox-close-small']").attr("type", "button");
				// Handle token input type
				$( ':input' ).each(function(  ) {
					tokens_proto = new pf.select2.tokens();
					if( $( this ).hasClass( 'pfTokens' )){
					tokens_proto.apply( $(this) );
				}
				});
				// Handling the text with autocomplete
				$('#popupForm').find(".autocompleteInput").each( function() {
					$(this).attachAutocomplete();
				});
				// Handle combobox input type
				// $( ':input' ).each(function(  ) {
				// 	combobox_proto = new pf.select2.combobox();
				// 	if( $( this ).hasClass( 'pfComboBox' )){
				// 	combobox_proto.apply($(this));
				// }
				// });

				// Handle the tree input types
				$('#popupForm').find(".pfTreeInput").each( function() {
					$(this).applyFancytree();
				});
				$('#popupForm').find(".pfRating").each( function() {
					$(this).applyRatingInput();
				});
				// Check if the event is only one day long
				// For current code - it is required to see if the event is one day long or not.
				// In future the code can be reduced and this if-else condition can be removed
				if ( flagOneDayEvent === true  ) {
					id_form = start.format() + "_fc" + counter;
					// Atuomatically set the event date value
					$( ':input[name="' + autoFillDay + '"]' ).val( start.format( 'DD' ) );
					$( ':input[name="' + autoFillYear + '"]' ).val( start.format( 'YYYY' ) );
					if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
						$( ':input[name="' + autoFillMonth + '"]' ).val( englishMonthNames[parseInt( start.format( 'MM' ) ) -1 ] );
					} else {
						$( ':input[name="' + autoFillMonth + '"]' ).val( start.format( 'MM' ) );
					}
					checkAndSave( 'single' );
					// Save all the data of the popup form and set the title, event date and the unique ID of the event
					$( "#form_submit" ).click(function( event ) {
						saveData( 'single' );
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventDate;
						eventDate = checkDateTime( dateFields, eventDate );
						eventData = {
							title: data[titleIndex].value,
							start: eventDate,
							end: reserveDate + 'T23:59:59',
							contents: data,
							id:id_form
						};

						counter++;
						$( '.fancybox-close-small' ).click();
						$( calendarIdSelector ).fullCalendar( 'renderEvent', eventData, true );
					});
				} else {
					id_form = start.format() + "_fc" + counter;
					currentEndDateMoment = moment(end);
					currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
					$( ':input[name="' + autoFillStartDay + '"]' ).val( Number(start.format( 'DD' )) );
					$( ':input[name="' + autoFillStartYear + '"]' ).val( start.format( 'YYYY' ) );
					if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
						$( ':input[name="' + autoFillStartMonth + '"]' ).val( englishMonthNames[parseInt( start.format( 'MM' ) ) -1 ] );
					} else {
						$( ':input[name="' + autoFillStartMonth + '"]' ).val( start.format( 'MM' ) );
					}
					$( ':input[name="' + autoFillEndDay + '"]' ).val( Number(currentEndDateMoment.format( 'DD' )) );
					$( ':input[name="' + autoFillEndYear + '"]' ).val( currentEndDateMoment.format( 'YYYY' ) );
					if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
						$( ':input[name="' + autoFillEndMonth + '"]' ).val( englishMonthNames[parseInt( currentEndDateMoment.format( 'MM' ) ) -1 ] );
					} else {
						$( ':input[name="' + autoFillEndMonth + '"]' ).val( currentEndDateMoment.format( 'MM' ) );
					}

					checkAndSave( 'multiple' );
					$( "#form_submit" ).click(function( event ) {
						saveData( 'multiple' );
						resetDateAndTime();
						setDateAndTime( dateStartFields, '1' );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventStartDate;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );
						resetDateAndTime();
						setDateAndTime( dateEndFields, '2' );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						reserveDate = eventEndDate;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );

						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement      = new Date(eventEndDate);
							next_date = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = next_date.getUTCFullYear() + '-' + padNumber(next_date.getUTCMonth() + 1) + '-' + padNumber(next_date.getUTCDate());
							eventEndDate = formatted;
						}
						eventData = {
							title: data[titleIndex].value,
							start: eventStartDate,
							end: eventEndDate,
							resourceEditable:true,
							contents: data,
							id:id_form
						};
						counter++;
						$( '.fancybox-close-small' ).click();
						$( calendarIdSelector ).fullCalendar( 'renderEvent', eventData, true );
					});
				}
			},

			// // Edit an event placed on the calendar by simply clicking on it
			eventClick: function( info ) {
				var content = $( calendarIdSelector ).fullCalendar( 'clientEvents', info.id );
				var form_contents = content[0].contents;
				var ratingArr = [];
				checkboxes_values = [];
				var paramName, rateSample = 0;
				// Open the popup form and populate it with the values to allow editing
				$.fancybox.open( updateEventPopup + delete_button + '</form>' );
				$("[class|='fancybox-close-small']").attr("type", "button");

				$('#popupForm').find(".pfTreeInput").each( function() {
					$(this).applyFancytree();
				});

				// Prepare the popup form for editing
				for( i =0 ; i<form_contents.length; i++ ) {
					temp = form_contents[i].name;
					paramName = temp.split('[')[2].split(']')[0];
					// If there is month field, set according the date format
					if( temp.includes('month') ) {
						if ( mw.config.get('wgAmericanDates') ) { //check for date-style format.
							if( englishMonthNames.indexOf( form_contents[i].value ) !== - 1  ) {
								$(':input[name="'+temp+'"]').val( form_contents[i].value );
							} else {
								$(':input[name="'+temp+'"]').val( englishMonthNames[ parseInt(form_contents[i].value) - 1] );
							}
						} else {
							$(':input[name="'+temp+'"]').val(form_contents[i].value);
						}
					} else if ( temp.includes('day') ) {
						$(':input[name="'+temp+'"]').val( Number(form_contents[i].value) );
					} else {
						if(fieldType[paramName] === 'radiobutton' || fieldType[paramName] === 'checkbox' || fieldType[paramName] === 'checkboxes' ) {
							if( fieldType[paramName] === 'radiobutton' ) {
								$(':input[value="' + form_contents[i].value + '"]').attr('checked',true);
							}
							if( fieldType[paramName] === 'checkbox' && !temp.includes('[is_checkbox]')) {
								temp = temp.replace('[value]','');
								temp = temp.replace('[is_checkbox]','');
								temp+='[is_checkbox]';
								var check_id1 = ($(':hidden[name="'+temp+'"]')[0].nextElementSibling.id);
								$('#'+ check_id1).prop('checked',form_contents[i].value);
								$(':hidden[name="'+temp+'"]').attr('value',0);
							}
							if( fieldType[paramName] === 'checkboxes' && !temp.includes('[is_list]')) {
								if( form_contents[i].value.includes(',') ) {
									checkboxes_values = form_contents[i].value.split(', ');
									for( var p = 0; p<checkboxes_values.length; p++ ) {
										$(':input[value="' + checkboxes_values[p] + '"]').attr('checked',true);
									}
								} else {
									$(':input[value="' + form_contents[i].value + '"]').attr('checked',true);
									// checkboxes_values.push(form_contents[i].value);
								}
							}
						} else {
							$(':input[name="'+temp+'"]').val(form_contents[i].value);
						}
						if( fieldType[paramName] === 'rating' ) {
							ratingArr.push(form_contents[i].value);
						}
					}
					// checkboxes_num.push(checkboxes_values);
				}
				// This is my little experiment to include rating input type to the calendar interface
				// This can improve in future version of this file.
				// I have saved the rating values in the ratingArr and just filling it in the 'each'
				// This may seem like a risky bet..
				$('#popupForm').find(".pfRating").each( function() {
					$(this).applyRatingInput( ratingArr[rateSample] );
					rateSample++;
				});
				// Handling the text with autocomplete
				$('#popupForm').find(".autocompleteInput").each( function() {
					$(this).attachAutocomplete();
				});

				// Handling token input type
				$( ':input' ).each(function() {
					tokens_proto = new pf.select2.tokens();
					if( $( this ).hasClass('pfTokens' )) {
					tokens_proto.apply($(this));
				}
				});
				// FIXME: This is not yet working correctly - possibly due
				// to z-index of fancybox popup or select2
				// $( ':input' ).each(function() {
				// 	combobox_proto = new pf.select2.combobox();
				// 	if( $( this ).hasClass( 'pfComboBox' )) {
				// 	combobox_proto.apply($(this));
				// }
				// });
				// Delete button for the existing event
				$( "#event_delete" ).click(function( event ) {
						$( calendarIdSelector ).fullCalendar('removeEvents',info.id);
						$( '.fancybox-close-small' ).click();
				});

				if ( flagOneDayEvent === true  ) {
					// Check if the date fields are left empty or not
					checkAndSave( 'single' );
					// Save everything again once the submit button is pressed
					$( "#form_submit" ).click(function( event ) {
						saveData( 'single' );
						resetDateAndTime();
						setDateAndTime( dateFields );
						eventDate = eventDateYear + '-' + padNumber(eventDateMonth) + '-' + padNumber(eventDateDay);
						eventDate = checkDateTime( dateFields, eventDate );
						info.title = data[titleIndex].value;
						info.contents = data;
						info.start = eventDate;
						$( '.fancybox-close-small') .click();
						$( calendarIdSelector ).fullCalendar( 'updateEvent' , info , true );
					});
				} else {
					var end = info.end;
					var start = info.start;
					checkAndSave( 'multiple' );
					$( "#form_submit" ).click(function( event ) {
						saveData( 'multiple' );
						resetDateAndTime();
						setDateAndTime( dateStartFields , '1' );
						eventStartDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						eventStartDate = checkDateTime( dateStartFields, eventStartDate );

						resetDateAndTime();
						setDateAndTime( dateEndFields , '2' );
						eventEndDate = eventDateYear + '-' + eventDateMonth + '-' + eventDateDay;
						eventEndDate = checkDateTime( dateEndFields, eventEndDate );

						if ( fieldType[eventEndDateField] === 'date' ) {
							dateElement      = new Date(eventEndDate);
							next_date = new Date(dateElement.setDate(dateElement.getDate() + 1));
							formatted = next_date.getUTCFullYear() + '-' + padNumber(next_date.getUTCMonth() + 1) + '-' + padNumber(next_date.getUTCDate());
							eventEndDate = formatted;
						}
						info.title = data[titleIndex].value;
						info.contents = data;
						info.start = eventStartDate;
						info.end = eventEndDate;
						$( '.fancybox-close-small') .click();
						$( calendarIdSelector ).fullCalendar( 'updateEvent', info, true );

					});
				}
			},

			eventResize: function(event) {
				event.start._i = event.start.format();
				event.end._i = event.end.format();
				currentEndDateMoment = moment(event.end);
				if( fieldType[eventStartDateField] === 'date' && event.allDay ) {
					currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
				}

				event_contents = event.contents;
				for( i = 0; i<event_contents.length; i++ ) {
						if ( event_contents[i].name === autoFillStartDay ) {
							event_contents[i].value = event.start.format('DD');
						} else if ( event_contents[i].name === autoFillStartMonth ) {
							if ( mw.config.get('wgAmericanDates') ) {
								event_contents[i].value = englishMonthNames[ parseInt(event.start.format('MM')) - 1 ];
							} else {
								event_contents[i].value = event.start.format('MM');
							}
						} else if ( event_contents[i].name === autoFillStartYear ) {
							event_contents[i].value = event.start.format('YYYY');
						} else if ( event_contents[i].name === autoFillEndDay ) {
							event_contents[i].value = currentEndDateMoment.format('DD');
						} else if ( event_contents[i].name === autoFillEndMonth ) {
							if ( mw.config.get('wgAmericanDates') ) {
								event_contents[i].value = englishMonthNames[ parseInt(currentEndDateMoment.format('MM')) - 1 ];
							} else {
								event_contents[i].value = currentEndDateMoment.format('MM');
							}
						} else if ( event_contents[i].name === autoFillEndYear ) {
							event_contents[i].value = currentEndDateMoment.format('YYYY');
						} else if ( event_contents[i].name === autoFillStartHour ) {
							event_contents[i].value = event.start.format('hh');
						} else if ( event_contents[i].name === autoFillStartMinute ) {
							event_contents[i].value = event.start.format('mm');
						} else if ( event_contents[i].name === autoFillStartSecond ) {
							event_contents[i].value = event.start.format('ss');
						} else if ( event_contents[i].name === autoFillStartAmPm24h ) {
							event_contents[i].value = ( event.start.format('t') === 'p' ? 'PM' : 'AM' );
						} else if ( event_contents[i].name === autoFillEndHour ) {
							event_contents[i].value = event.end.format('hh');
						} else if ( event_contents[i].name === autoFillEndMinute ) {
							event_contents[i].value = event.end.format('mm');
						} else if ( event_contents[i].name === autoFillEndSecond ) {
							event_contents[i].value = event.end.format('ss');
						} else if ( event_contents[i].name === autoFillEndAmPm24h ) {
							event_contents[i].value = ( event.end.format('t') === 'p' ? 'PM' : 'AM' );
						}
				}

				event.contents = event_contents;
				$( calendarIdSelector ).fullCalendar('updateEvent',event);

			},

			eventDrop: function( event, delta, revertFunc ) {
				var old_event = $( calendarIdSelector ).fullCalendar( 'clientEvents', event.id );
				$( calendarIdSelector ).fullCalendar('removeEvents',old_event[0].id);
				event.start._i = event.start.format();

				if( flagOneDayEvent === false ) {
					if( event.end !== null ) {
						event.end._i = event.end.format() ;
						currentEndDateMoment = moment(event.end);
						if( fieldType[eventStartDateField] === 'date' && event.allDay ) {
							currentEndDateMoment = currentEndDateMoment.subtract(1 , 'days');
						}
						event_contents = event.contents;
						for( i = 0; i<event_contents.length; i++ ) {
							if ( event_contents[i].name === autoFillStartDay ) {
								event_contents[i].value = event.start.format('DD');
							} else if ( event_contents[i].name === autoFillStartMonth ) {
								if ( mw.config.get('wgAmericanDates') ) {
									event_contents[i].value = englishMonthNames[ parseInt( event.start.format('MM')) - 1 ];
								} else {
									event_contents[i].value = event.start.format('MM');
								}
							} else if ( event_contents[i].name === autoFillStartYear ) {
								event_contents[i].value = event.start.format('YYYY');
							} else if ( event_contents[i].name === autoFillEndDay ) {
								event_contents[i].value = currentEndDateMoment.format('DD');
							} else if ( event_contents[i].name === autoFillEndMonth ) {
								if ( mw.config.get('wgAmericanDates') ) {
									event_contents[i].value = englishMonthNames[ parseInt(currentEndDateMoment.format('MM')) - 1 ];
								} else {
									event_contents[i].value = currentEndDateMoment.format('MM');
								}
							} else if ( event_contents[i].name === autoFillEndYear ) {
								event_contents[i].value = currentEndDateMoment.format('YYYY');
							} else if ( event_contents[i].name === autoFillStartHour ) {
								event_contents[i].value = event.start.format('hh');
							} else if ( event_contents[i].name === autoFillStartMinute ) {
								event_contents[i].value = event.start.format('mm');
							} else if ( event_contents[i].name === autoFillStartSecond ) {
								event_contents[i].value = event.start.format('ss');
							} else if ( event_contents[i].name === autoFillStartAmPm24h ) {
								event_contents[i].value = ( event.start.format('t') ==='p' ? 'PM' : 'AM' );
							} else if ( event_contents[i].name === autoFillEndHour ) {
								event_contents[i].value = event.end.format('hh');
							} else if ( event_contents[i].name === autoFillEndMinute ) {
								event_contents[i].value = event.end.format('mm');
							} else if ( event_contents[i].name === autoFillEndSecond ) {
								event_contents[i].value = event.end.format('ss');
							} else if ( event_contents[i].name === autoFillEndAmPm24h ) {
								event_contents[i].value = ( event.end.format('t') ==='p' ? 'PM' : 'AM' );
							}
						}
					} else {
						event_contents = event.contents;
						for( i = 0; i<event_contents.length; i++ ) {
								if ( event_contents[i].name === autoFillStartDay ) {
									event_contents[i].value = event.start.format('DD');
								} else if ( event_contents[i].name === autoFillStartMonth ) {
									if ( mw.config.get('wgAmericanDates') ) {
										event_contents[i].value = englishMonthNames[ parseInt( event.start.format('MM')) - 1 ];
									} else {
										event_contents[i].value = event.start.format('MM');
									}
								} else if ( event_contents[i].name === autoFillStartYear ) {
									event_contents[i].value = event.start.format('YYYY');
								} else if ( event_contents[i].name === autoFillEndDay ) {
									event_contents[i].value = event.start.format('DD');
								} else if ( event_contents[i].name === autoFillEndMonth ) {
									if ( mw.config.get('wgAmericanDates') ) {
										event_contents[i].value = englishMonthNames[ parseInt( event.start.format('MM')) - 1 ];
									} else {
										event_contents[i].value = event.start.format('MM');
									}
								} else if ( event_contents[i].name === autoFillEndYear ) {
									event_contents[i].value = event.start.format('YYYY');
								}
						}
					}

			} else {
				event_contents = event.contents;
				event.end._i = event.start.format( 'YYYY' ) + '-' + event.start.format('MM') + '-' + event.start.format('DD') + "T23:59:59";
				for( i = 0; i<event_contents.length; i++ ) {
					if ( event_contents[i].name === autoFillDay ) {
						event_contents[i].value = event.start.format('DD');
					} else if ( event_contents[i].name === autoFillMonth ) {
						if ( mw.config.get('wgAmericanDates') ) {
							event_contents[i].value = englishMonthNames[ parseInt( event.start.format('MM')) - 1 ];
						} else {
							event_contents[i].value = event.start.format('MM');
						}
					} else if ( event_contents[i].name === autoFillYear ) {
						event_contents[i].value = event.start.format('YYYY');
						} else if ( event_contents[i].name === autoFillHour ) {
							event_contents[i].value = event.start.format('hh');
						} else if ( event_contents[i].name === autoFillMinute ) {
							event_contents[i].value = event.start.format('mm');
						} else if ( event_contents[i].name === autoFillSecond ) {
							event_contents[i].value = event.start.format('ss');
						} else if ( event_contents[i].name === autoFillAmPm24h ) {
							event_contents[i].value = ( event.start.format('t') ==='p' ? 'PM' : 'AM' );
						}
				}
			}
			$( calendarIdSelector ).fullCalendar( 'renderEvent', event, true );
			},
			displayEventEnd: isEventEndDateTime,
			timeFormat: 'H(:mm:ss)t',
			eventDurationEditable: true,
			displayEventTime: isEventStartTime
		});
		$('#fullCalendarLoading').css("display", "none");

		// Handle the "Save page" button
		$( "#pfForm" ).submit(function( event ) {
			all_events = $( calendarIdSelector ).fullCalendar('clientEvents');
			var dateValue = '';
			var day = '';
			var month = '';
			var year = '';
			var hour = '';
			var minute = '';
			var second = '';
			var ampm24h = ' ';
			for( i =0;i<all_events.length;i++ ) {
				var eventContent = all_events[i].contents;
				var finalFieldValues = [];
				for( var ii=0; ii<calendarParams[templateNameCopy].length; ii++ ) {
					parameterName = calendarParams[templateNameCopy][ii].name;
					var inputValue = '';
					if( fieldType[parameterName] === "date" ) {
						dateValue = '';
						day = '';
						month = '';
						year = '';
						for( j=0;j<eventContent.length;j++ ) {
							if( eventContent[j].name.includes('['+parameterName+']') ) {
								if( eventContent[j].name.includes('[day]') ) {
									day =  eventContent[j].value.replace(/(^|-)0+/g, "$1");
								} else if( eventContent[j].name.includes('[year]') ) {
									year =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[month]') ) {
									month =  eventContent[j].value;
								}
							}
						}
						if( mw.config.get('wgAmericanDates') ) {
							if( englishMonthNames.indexOf(month) ) {
								dateValue = month + ' ' + day + ', ' + year;
							} else if ( englishMonthNames.indexOf(month) === -1 ) {
								dateValue = englishMonthNames[ parseInt(month) - 1 ] + ' ' + day + ', ' + year;
							}
						} else {
							dateValue = year + '/' + month + '/' + padNumber(day);
							if( year === '' && month === '' && day === '' || year === '' && month === '' ) {
								dateValue = '';
							} else if ( day === '' ) {
								if( month === '' && year !== '' ) {
									dateValue = month;
								} else if ( month !== '' && year === '' ) {
									dateValue = year;
								} else if ( month !== '' && year !== '' ) {
									dateValue = month + ' ' + year;
								}
							}
						}
						inputValue = dateValue;
					} else if ( fieldType[parameterName] === "datetime" ) {
						dateValue = '';
						day = '';
						month = '';
						year = '';
						hour = '';
						minute = '';
						second = '';
						ampm24h = ' ';
						for( j=0;j<eventContent.length;j++ ) {
							if( eventContent[j].name.includes('['+parameterName+']') ) {
								if( eventContent[j].name.includes('[day]') ) {
									day =  eventContent[j].value.replace(/(^|-)0+/g, "$1");
								} else if( eventContent[j].name.includes('[year]') ) {
									year =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[month]') ) {
									month =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[hour]') ) {
									hour =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[minute]') ) {
									minute =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[second]') ) {
									second =  eventContent[j].value;
								} else if( eventContent[j].name.includes('[ampm24h]') ) {
									ampm24h =  (eventContent[j].value !== '' ) ? eventContent[j].value : ' ';
								}
							}
						}
						if( mw.config.get('wgAmericanDates') ) {
							if( englishMonthNames.indexOf(month) ) {
								dateValue = month + ' ' + day + ', ' + year + ' ' + padNumber(hour) + ':' + padNumber(minute) + ':' + padNumber(second) + ' ' + ampm24h;
							} else if ( englishMonthNames.indexOf(month) === -1 ) {
								dateValue = englishMonthNames[ parseInt(month) - 1 ] + ' ' + day + ', ' + year + ' ' + padNumber(hour) + ':' + padNumber(minute) + ':' + padNumber(second) + ' ' + ampm24h;
							}
						} else {
							dateValue = year + '/' + month + '/' + padNumber( day ) + ' ' + padNumber(hour) + ':' + padNumber(minute) + ':' + padNumber(second) + ' ' + ampm24h;
						}
						inputValue = dateValue;
					} else {
						var checkboxes_final = '';
						checkboxes_num= [];
						if( fieldType[parameterName] === 'checkboxes' ) {
							for( j=0;j<eventContent.length;j++ ) {
								if( eventContent[j].name.includes('['+parameterName+']') && !eventContent[j].name.includes('[is_list]') ) {
									checkboxes_num.push(eventContent[j].value);
								}
							}
							for(var t = 0; t< checkboxes_num.length;t++ ) {
								if( t< checkboxes_num.length -1  ) {
									checkboxes_final += checkboxes_num[t] + ', ';
								} else {
									checkboxes_final +=checkboxes_num[t];
								}
							}
							inputValue = checkboxes_final;
						} else {
							for( j=0;j<eventContent.length;j++ ) {
								if( eventContent[j].name.includes('['+parameterName+']') ) {
									inputValue = eventContent[j].value;
								}
							}
						}
					}
					var inputName = templateNameCopy + '['+ (i+1) +'][' + parameterName + ']';
					finalFieldValues[inputName] = inputValue;
					$('<input>').attr( 'type', 'hidden' ).attr( 'name', inputName ).attr( 'value',finalFieldValues[inputName] ).appendTo( '#pfForm' );
				}
			}
			for( var k =0;k<eventsNoDate.length; k++ ) {
				var index = i+1;
				for( var jj=0; jj<calendarParams[templateNameCopy].length; jj++ ) {
					parameterName = calendarParams[templateNameCopy][jj].name;
					var entryName = templateNameCopy + '['+ (index) +'][' + parameterName + ']';
					$('<input>').attr( 'type', 'hidden' ).attr( 'name', entryName ).attr( 'value',eventsNoDate[k][parameterName] ).appendTo( '#pfForm' );
				}
			}
		});

	});
}( jQuery, mediaWiki, pf ) );
