import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'Biding';
  imie:string="Kototos";
  zdjecie:string="assets/kot.png";
  zdjecie2:string="assets/kot2.png";
  zdjecie3:string="assets/kot3.png";
  colore:string="blue";
  fonte:string="30px";
  kolor:string="";
  nazwisko:string="";
  KolorTla() {
    if(this.kolor!=""){
      this.kolor=this.kolor=="green"?"red":"green";
    }else{this.kolor="green";}
    
  }
}
