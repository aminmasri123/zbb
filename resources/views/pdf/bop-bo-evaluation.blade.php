<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auswertungs- und Bewertungsbogen 2024</title>
    <style>
          @page {
                size: A4;
                margin: 0mm; /* Passe die Seitenränder an */
            }
        body {
            font-family: 'Comic Sans MS', 'Patrick Hand', cursive; /* Ähnliche Schriftarten */
            width: 20cm;
            width: 190mm; /* Breite des Inhaltsbereichs */
            height: 277mm; /* Höhe des Inhaltsbereichs */
            margin: 0 auto;
        }
        .page-break {
            page-break-after: always;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding-top: 3px;
            padding-bottom: 3px;

        }

        h1 {
            font-size: 24px; /* Schriftgröße anpassen */
            text-align: center;
            color: #ff8500; /* Füllfarbe */
            -webkit-text-stroke: 1px black; /* Rahmen: Dicke und Farbe */
            letter-spacing: 1px; /* Buchstabenabstand */
            width: 15cm;
        }

        .format-breite{
            width: 19cm;
        }
        .center{
            text-align: center;
        }
        .bloc{
            text-align: center;
        }
        .w-block-info{
            width: 4.5cm;
        }
        .bloc-info{
            display: inline-block;
            text-align: left;
            margin-left:10px;
        }
        .info{
            font-size: 12px;
            font-weight: bold;
        }
        .feld{
            background-color: white;
            border: 1px solid black;
            padding: 5px;
            min-height: 0.5cm;
        }
        .beobachtung{
            font-size: 16px;
            padding-left: 1.5cm;
        }
        .rating-container2 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 2px solid black;
            border-radius: 10px;
            width: 250px;
            background-color: white;
            height: 1.5cm;
        }

        .rating-container {
            align-items: center;
            padding: 10px;
            border: 2px solid black;
            border-radius: 10px;
            width: 250px;
            background-color: white;
            height: 1.5cm;
        }

        .checkbox-star {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-bottom: 5px;
            cursor: pointer;
        }

        .star {
            font-size: 24px;
            color: gold;
        }

        .star.checked {
            color: black;
        }

        .daten-input{

        }


    </style>
</head>
<body>
    @foreach ($alle_teilnehmer as $index => $teilnehmer)
            <div class="container format-breite">
                <header>
                    <table >
                        <tr style="border: none">
                            <td style="border: none"><img class="logo-header" src="{{resource_path('documents/bop/logo.png')}}" alt="Logo-ZBB" style="width: 2.6cm; height: 1.1cm;"></td>
                            <td style="border: none"><img src="{{resource_path('documents/bop/einschaetzung_der_kompetenzen.png')}}" style="width: 15.4cm" alt=""></td>
                        </tr>
                    </table>
                </header>
                <section>
                    <table class="format-breite">
                        <tr style="background-color: rgb(252, 196, 25)">
                            <td colspan="2" style="border: 1px solid black; padding-top:15px">
                                <div class="bloc">
                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Vorname</span></div>
                                        <div style="width: 5cm" class="feld"><span class="daten-input">{{$teilnehmer->vorname}}</span></div>
                                    </div>
                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Nachname</span></div>
                                        <div style="width: 5cm" class="feld"><span class="daten-input">{{$teilnehmer->nachname}}</span></div>
                                    </div>
                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Klasse</span></div>
                                        <div style="width: 2cm" class="feld">{{$teilnehmer->klasse}}</div>
                                    </div>
                                    <div class="bloc-info">
                                        <div><span class="info">Datum</span></div>
                                        <div style="width: 4cm" class="feld">{{$teilnehmer->enddatum}}</div>
                                    </div>
                                </div>
                                <div class="bloc">
                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Fachkraft</span></div>
                                        <div style="width: 5cm" class="feld">{{$teilnehmer->anleiter_name}}</div>
                                    </div>

                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Schule</span></div>
                                        <div style="width: 5.5cm" class="feld">{{$teilnehmer->schule_name}}</div>
                                    </div>
                                    <div class="bloc-info .w-block-info">
                                        <div><span class="info">Berufsfeld</span></div>
                                        <div style="width: 6cm" class="feld">{{$teilnehmer->bereich_name}}</div>
                                    </div>

                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="width: 60%; border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >1. Einhaltung der Arbeitszeitregeln</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einhaltung_der_regeln == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einhaltung_der_regeln == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einhaltung_der_regeln == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einhaltung_der_regeln == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einhaltung_der_regeln == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >2. Verständnis des Arbeitsauftrages</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->verständnis_des_arbeitsauftrages == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->verständnis_des_arbeitsauftrages == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->verständnis_des_arbeitsauftrages == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->verständnis_des_arbeitsauftrages == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->verständnis_des_arbeitsauftrages == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >3. Bereitschaft der Auftragsübernahme (Arbeitsmotivation)</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->bereitschaft_der_auftragsübernahme == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->bereitschaft_der_auftragsübernahme == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->bereitschaft_der_auftragsübernahme == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->bereitschaft_der_auftragsübernahme == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->bereitschaft_der_auftragsübernahme == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >4. Selbstständigkeit in der Durchführung der Aufgabe</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->selbständigkeit_in_der_durchführung_der_aufgabe == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->selbständigkeit_in_der_durchführung_der_aufgabe == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->selbständigkeit_in_der_durchführung_der_aufgabe == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->selbständigkeit_in_der_durchführung_der_aufgabe == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->selbständigkeit_in_der_durchführung_der_aufgabe == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >5. Freude an der Arbeit</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->freude_an_der_arbeit == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->freude_an_der_arbeit == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->freude_an_der_arbeit == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->freude_an_der_arbeit == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->freude_an_der_arbeit == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >6. Methodisches Herangehen an die Aufgabe</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->methodisches_herangehen_an_die_aufgabenerledigung == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->methodisches_herangehen_an_die_aufgabenerledigung == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->methodisches_herangehen_an_die_aufgabenerledigung == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->methodisches_herangehen_an_die_aufgabenerledigung == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->methodisches_herangehen_an_die_aufgabenerledigung == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >7. Sorgfältigkeit in der Aufgabenerledigung (präzise, genaue Aufgabenerledigung)</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->sorgfäligkeit_in_der_aufgabenerledigung == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->sorgfäligkeit_in_der_aufgabenerledigung == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->sorgfäligkeit_in_der_aufgabenerledigung == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->sorgfäligkeit_in_der_aufgabenerledigung == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->sorgfäligkeit_in_der_aufgabenerledigung == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >8. Korrekter Umgang mit Werkzeug und Material</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->korrekter_umgang_mit_werkzeug_und_material == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->korrekter_umgang_mit_werkzeug_und_material == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->korrekter_umgang_mit_werkzeug_und_material == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->korrekter_umgang_mit_werkzeug_und_material == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->korrekter_umgang_mit_werkzeug_und_material == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black;">
                                <div class="beobachtung">
                                    <div><span >9. Ordnung am Arbeitsplatz</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->ordnung_am_arbeitsplatz == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->ordnung_am_arbeitsplatz == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->ordnung_am_arbeitsplatz == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->ordnung_am_arbeitsplatz == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->ordnung_am_arbeitsplatz == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="border-left:1px solid black; border-bottom: 1px solid black;">
                                <div class="beobachtung">
                                    <div><span >10. Soziale Kompetenzen (angemessene Umgangsformen, Respekt, Teamfähigkeit)</span></div>
                                </div>
                            </td>
                            <td style="border-right:1px solid black;border-bottom: 1px solid black;">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 2px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->soziale_kompetenzen == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->soziale_kompetenzen == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->soziale_kompetenzen == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->soziale_kompetenzen == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->soziale_kompetenzen == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </section>
                <section>
                    <hr style="border: none; border-top: 2px solid #ff8500; width: 100%;">
                    <table>
                        <tr>
                            <td style="width: 60%;">
                                <div class="beobachtung">
                                    <span >11. Einschätzung der Befähigung und Eignung Berufsorientierung in dem jeweiligen Berufsfeld</span>
                                </div>
                            </td>
                            <td class="center">
                                <table style="width:250px">
                                    <tr>
                                        <td>
                                            <div style="border: 4px solid black; border-radius: 10px; overflow: hidden; width: 250px; background-color: white;">
                                                <table>
                                                    <tr>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einschätzung_der_befähigung_und_eignung_zur_berufsorientierung == '5' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einschätzung_der_befähigung_und_eignung_zur_berufsorientierung == '4' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einschätzung_der_befähigung_und_eignung_zur_berufsorientierung == '3' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einschätzung_der_befähigung_und_eignung_zur_berufsorientierung == '2' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                        <td class="center">
                                                            <div style="width:0.5cm; height:0.5cm; border:1px solid black; border-radius:4px; margin:auto">
                                                                <span>{{$teilnehmer->einschätzung_der_befähigung_und_eignung_zur_berufsorientierung == '1' ? 'X' : ''}}</span>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td style="text-align: center;padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.86cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding:0px">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.82cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.73cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.64cm; margin: 0 auto;">
                                                        </td>
                                                        <td style="text-align: center; padding: 0px;">
                                                            <img src="{{resource_path('documents/bop/star.png')}}" alt="stern" style="width: 0.59cm; margin: 0 auto;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>


                        </tr>
                    </table>
                </section>
                <p style="text-align: center"><b>Legende:</b> "Hervorragend" <img src="{{resource_path('documents/bop/star.png')}}" alt="stern"  style="width: 0.86cm"> "Sehr gut" <img src="{{resource_path('documents/bop/star.png')}}" alt="stern"  style="width: 0.82cm"> "Gut" <img src="{{resource_path('documents/bop/star.png')}}" alt="stern"  style="width: 0.73cm"> "Ganz ok" <img src="{{resource_path('documents/bop/star.png')}}" alt="stern"  style="width: 0.64cm"> "Da geht noch was" <img src="{{resource_path('documents/bop/star.png')}}" alt="stern"  style="width: 0.59cm"></p>
            </div>
        <!-- Seitenumbruch nach jedem Teilnehmer -->
        @if ($index < $alle_teilnehmer->count() - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
