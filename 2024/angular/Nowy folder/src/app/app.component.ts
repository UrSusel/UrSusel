import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})
export class AppComponent {
  title = 'projektegzamin';
  kurs:any[]=["Programowanie w C#","Angular dla początkujących","Kurs Django"]
  imie:string="";
  nrk:number=0;
  liczba:any=3;
  film:string="";
  FilmRodzaje:any[]=["","Komedia","Obyczajowy","Horror"];
  zmienna:string="";
  logow(){
    console.log(this.imie);
    console.log("Kurs: "+this.kurs[this.nrk-1]);
  }
  Zapisz(){
    console.log("Film: "+this.film+" Rodzaj: "+this.zmienna)
  }
}
