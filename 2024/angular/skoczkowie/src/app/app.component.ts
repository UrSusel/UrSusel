import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'SKoczkowie';
  skoczkowie:any=[
    {zdjecie: "assets/ahonen.jpg" ,opis: "Ahonen"}, 
    {zdjecie: "assets/ammann.jpg" ,opis: "Ammann"},
    {zdjecie: "assets/kasai.jpg" ,opis: "Kasai"},
    {zdjecie: "assets/kot.jpg" ,opis: "Kot"},
    {zdjecie: "assets/kraft.jpg" ,opis: "Kraft"},
    {zdjecie: "assets/kranjec.jpg" ,opis: "Krajnce"},
    {zdjecie: "assets/kubacki.jpg" ,opis: "Kubacki"},
    {zdjecie: "assets/malysz.jpg" ,opis: "Malysz"},
    {zdjecie: "assets/schlierenzauer.jpg" ,opis: "Schlierenzauer"},
    {zdjecie: "assets/schmitt.jpg" ,opis: "Schmitt"},
    {zdjecie: "assets/stoch.jpg" ,opis: "Stoch"},
    {zdjecie: "assets/zyla.jpg" ,opis: "Żyla"}
  ]
}
