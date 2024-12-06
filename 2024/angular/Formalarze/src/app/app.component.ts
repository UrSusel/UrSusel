import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'Formmalarze';
  wybranePole: boolean[]=[];
  wybranePole1: boolean[]=[];
  wartosci:any[]=[{id: 1, wartosc: "Artur"},{id: 2,wartosc:"Daniel"},{id: 3,wartosc:"Ksawier"},{id: 4,wartosc:"Janek"},{id: 5,wartosc:"Jezus"}];
  prodkukty:any[]=[{wartosc:"Pralka"},{wartosc:"Lodówka"},{wartosc:"Wirówka"},{wartosc:"Zamrażarka"},{wartosc:"Kuchenka"}]
  tak:boolean=false;
  wyswietlanie(){
    this.tak=true;
  }
}
