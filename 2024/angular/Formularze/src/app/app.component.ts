import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'Formularze';
  imie: string="";
  email: string="";
  produkt: string="";
  Ilosc: number=0;
  stan: string="";
  wiadomosc: string="";
  konsole(){
    console.log("imie: "+this.imie+" email: "+this.email+" proukt: "+this.produkt+" Ilosc: "+this.Ilosc+" Stan: "+this.stan+" wiadomosc: "+this.wiadomosc);
    
  };

}
